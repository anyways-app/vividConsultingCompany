<?php
/**
 * vividConsulting.info — API: Template Library
 *
 * GET /qa/api/templates.php
 * Returns JSON array of templates the user can see.
 * Each item includes a flag indicating whether the user has access.
 * Returns 401 if not authenticated.
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../auth.php';

$user = auth_current_user();

if (!$user) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

$tier = $user['subscription_tier'];  // 'free', 'consultant', or 'enterprise'

// ── Stub template catalog ─────────────────────────────────────
// Replace this with a database query when the template catalog is built.
$templates = [
    [
        'id'          => 'tpl-001',
        'name'        => 'SAP GRC 12.0 Access Control Configuration Workbook',
        'category'    => 'SAP GRC',
        'tier'        => 'free',
        'format'      => 'XLSX',
        'description' => 'Step-by-step configuration worksheet for GRC AC 12.0 including BRFplus rules, connector setup, and workflow variants.',
    ],
    [
        'id'          => 'tpl-002',
        'name'        => 'SoD Ruleset — Cross-Module (FI/CO/MM/SD)',
        'category'    => 'Compliance',
        'tier'        => 'consultant',
        'format'      => 'XLSX',
        'description' => 'Pre-built segregation of duties ruleset covering 320+ risk pairs across SAP FI, CO, MM, and SD modules.',
    ],
    [
        'id'          => 'tpl-003',
        'name'        => 'SAP Security Role Design Template',
        'category'    => 'Security',
        'tier'        => 'free',
        'format'      => 'XLSX',
        'description' => 'Role naming convention, authorization object mapping, and composite role structure template.',
    ],
    [
        'id'          => 'tpl-004',
        'name'        => 'GRC Implementation Project Plan',
        'category'    => 'Project Management',
        'tier'        => 'consultant',
        'format'      => 'PPTX',
        'description' => 'Full implementation project plan with milestones, dependencies, RACI, and risk register for GRC AC/PC/RM.',
    ],
    [
        'id'          => 'tpl-005',
        'name'        => 'S/4HANA Security Migration Assessment',
        'category'    => 'SAP Security',
        'tier'        => 'consultant',
        'format'      => 'DOCX',
        'description' => 'Assessment template for evaluating security model readiness during ECC to S/4HANA migration.',
    ],
    [
        'id'          => 'tpl-006',
        'name'        => 'Emergency Access Management (EAM) Procedure',
        'category'    => 'SAP GRC',
        'tier'        => 'free',
        'format'      => 'DOCX',
        'description' => 'Standard operating procedure for firefighter ID management, log review, and compliance reporting.',
    ],
    [
        'id'          => 'tpl-007',
        'name'        => 'SailPoint IIQ Deployment Checklist',
        'category'    => 'Identity',
        'tier'        => 'consultant',
        'format'      => 'XLSX',
        'description' => 'Comprehensive deployment checklist covering connectors, certification campaigns, provisioning policies, and lifecycle events.',
    ],
    [
        'id'          => 'tpl-008',
        'name'        => 'Client Workshop Deck — GRC Roadmap',
        'category'    => 'Presentations',
        'tier'        => 'consultant',
        'format'      => 'PPTX',
        'description' => '45-slide presentation template for GRC roadmap workshops with executive summary, current-state analysis, and phased recommendations.',
    ],
];

// Add access flag based on user tier
$tierRank = ['free' => 0, 'consultant' => 1, 'enterprise' => 2];
$userRank = $tierRank[$tier] ?? 0;

$result = array_map(function ($tpl) use ($userRank, $tierRank) {
    $tpl['accessible'] = $userRank >= ($tierRank[$tpl['tier']] ?? 0);
    return $tpl;
}, $templates);

echo json_encode([
    'user_tier'  => $tier,
    'templates'  => $result,
]);
