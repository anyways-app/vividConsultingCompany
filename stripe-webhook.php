<?php
/**
 * vividConsulting.info — Stripe Webhook Handler
 *
 * Receives events from Stripe and updates subscriptions + user tiers.
 * Endpoint: POST /qa/stripe-webhook.php
 *
 * Events handled:
 *   checkout.session.completed
 *   customer.subscription.updated
 *   customer.subscription.deleted
 *   invoice.payment_failed
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/vendor/autoload.php';

$cfg = require __DIR__ . '/config.php';

// ── Read and verify webhook signature ─────────────────────────
$payload   = file_get_contents('php://input');
$sigHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

try {
    $event = \Stripe\Webhook::constructEvent(
        $payload,
        $sigHeader,
        $cfg['STRIPE_WEBHOOK_SECRET']
    );
} catch (\UnexpectedValueException $e) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid payload']);
    exit;
} catch (\Stripe\Exception\SignatureVerificationException $e) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid signature']);
    exit;
}

$pdo = vc_db();

// ── Route by event type ───────────────────────────────────────
switch ($event->type) {

    // ─── Checkout completed: create subscription record ───────
    case 'checkout.session.completed':
        $session = $event->data->object;

        $userId = $session->metadata->vc_user_id ?? null;
        if (!$userId) {
            error_log('[stripe-webhook] checkout.session.completed missing vc_user_id');
            break;
        }

        // Store Stripe customer ID on user
        if (!empty($session->customer)) {
            $pdo->prepare('UPDATE users SET stripe_customer_id = :cid WHERE id = :uid')
                ->execute(['cid' => $session->customer, 'uid' => $userId]);
        }

        // Retrieve the subscription from Stripe for period details
        if (!empty($session->subscription)) {
            \Stripe\Stripe::setApiKey($cfg['STRIPE_SECRET_KEY']);
            $sub = \Stripe\Subscription::retrieve($session->subscription);

            $pdo->prepare('
                INSERT INTO subscriptions (user_id, stripe_subscription_id, stripe_price_id, status, current_period_start, current_period_end)
                VALUES (:uid, :sid, :pid, :status, to_timestamp(:ps), to_timestamp(:pe))
                ON CONFLICT (stripe_subscription_id) DO UPDATE SET
                    status = EXCLUDED.status,
                    current_period_start = EXCLUDED.current_period_start,
                    current_period_end = EXCLUDED.current_period_end,
                    updated_at = now()
            ')->execute([
                'uid'    => $userId,
                'sid'    => $sub->id,
                'pid'    => $sub->items->data[0]->price->id ?? '',
                'status' => $sub->status,
                'ps'     => $sub->current_period_start,
                'pe'     => $sub->current_period_end,
            ]);

            // Upgrade user tier
            if ($sub->status === 'active' || $sub->status === 'trialing') {
                $pdo->prepare("UPDATE users SET subscription_tier = 'consultant' WHERE id = :uid")
                    ->execute(['uid' => $userId]);
            }
        }
        break;

    // ─── Subscription updated ─────────────────────────────────
    case 'customer.subscription.updated':
        $sub = $event->data->object;

        $stmt = $pdo->prepare('
            UPDATE subscriptions SET
                status = :status,
                stripe_price_id = :pid,
                current_period_start = to_timestamp(:ps),
                current_period_end = to_timestamp(:pe),
                cancel_at_period_end = :cap,
                updated_at = now()
            WHERE stripe_subscription_id = :sid
        ');
        $stmt->execute([
            'status' => $sub->status,
            'pid'    => $sub->items->data[0]->price->id ?? '',
            'ps'     => $sub->current_period_start,
            'pe'     => $sub->current_period_end,
            'cap'    => $sub->cancel_at_period_end ? 'true' : 'false',
            'sid'    => $sub->id,
        ]);

        // Update user tier based on new status
        $userId = $sub->metadata->vc_user_id ?? null;
        if ($userId) {
            $tier = in_array($sub->status, ['active', 'trialing']) ? 'consultant' : 'free';
            $pdo->prepare("UPDATE users SET subscription_tier = :tier WHERE id = :uid")
                ->execute(['tier' => $tier, 'uid' => $userId]);
        }
        break;

    // ─── Subscription deleted (cancelled + period ended) ──────
    case 'customer.subscription.deleted':
        $sub = $event->data->object;

        $pdo->prepare("
            UPDATE subscriptions SET status = 'canceled', updated_at = now()
            WHERE stripe_subscription_id = :sid
        ")->execute(['sid' => $sub->id]);

        // Downgrade user to free
        $userId = $sub->metadata->vc_user_id ?? null;
        if ($userId) {
            $pdo->prepare("UPDATE users SET subscription_tier = 'free' WHERE id = :uid")
                ->execute(['uid' => $userId]);
        }
        break;

    // ─── Payment failed ───────────────────────────────────────
    case 'invoice.payment_failed':
        $invoice = $event->data->object;
        error_log(sprintf(
            '[stripe-webhook] Payment failed for customer %s, subscription %s, amount %s',
            $invoice->customer ?? 'unknown',
            $invoice->subscription ?? 'unknown',
            ($invoice->amount_due ?? 0) / 100
        ));

        // Optionally update subscription status
        if (!empty($invoice->subscription)) {
            $pdo->prepare("
                UPDATE subscriptions SET status = 'past_due', updated_at = now()
                WHERE stripe_subscription_id = :sid
            ")->execute(['sid' => $invoice->subscription]);
        }
        break;

    default:
        // Unhandled event type — log and acknowledge
        error_log('[stripe-webhook] Unhandled event type: ' . $event->type);
}

// Always return 200 to Stripe
http_response_code(200);
echo json_encode(['received' => true]);
