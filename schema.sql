-- ============================================================
-- vividConsulting.info — PostgreSQL Database Schema
-- Version: 1.0
-- Run: psql -U <user> -d <dbname> -f schema.sql
-- ============================================================

-- Enable UUID generation
CREATE EXTENSION IF NOT EXISTS "pgcrypto";

-- ============================================================
-- ENUM TYPES
-- ============================================================

CREATE TYPE subscription_tier AS ENUM ('free', 'consultant', 'enterprise');
CREATE TYPE auth_provider     AS ENUM ('google', 'microsoft', 'apple');

-- ============================================================
-- USERS
-- ============================================================

CREATE TABLE users (
    id                UUID        NOT NULL DEFAULT gen_random_uuid(),
    email             TEXT        NOT NULL,
    email_verified    BOOLEAN     NOT NULL DEFAULT FALSE,
    display_name      TEXT,
    given_name        TEXT,
    family_name       TEXT,
    avatar_url        TEXT,
    locale            TEXT,
    subscription_tier subscription_tier NOT NULL DEFAULT 'free',
    stripe_customer_id TEXT,
    created_at        TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at        TIMESTAMPTZ NOT NULL DEFAULT now(),
    last_login_at     TIMESTAMPTZ,

    CONSTRAINT users_pk    PRIMARY KEY (id),
    CONSTRAINT users_email UNIQUE (email)
);

CREATE INDEX idx_users_email ON users (email);

-- ============================================================
-- AUTH IDENTITIES  (one user → many providers)
-- ============================================================

CREATE TABLE auth_identities (
    id               UUID        NOT NULL DEFAULT gen_random_uuid(),
    user_id          UUID        NOT NULL,
    provider         auth_provider NOT NULL,
    provider_user_id TEXT        NOT NULL,
    provider_email   TEXT,
    provider_data    JSONB,
    created_at       TIMESTAMPTZ NOT NULL DEFAULT now(),

    CONSTRAINT auth_identities_pk          PRIMARY KEY (id),
    CONSTRAINT auth_identities_user_fk     FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT auth_identities_provider_uq UNIQUE (provider, provider_user_id)
);

CREATE INDEX idx_auth_identities_user     ON auth_identities (user_id);
CREATE INDEX idx_auth_identities_provider ON auth_identities (provider, provider_user_id);

-- ============================================================
-- SUBSCRIPTIONS  (mirrors Stripe subscription objects)
-- ============================================================

CREATE TABLE subscriptions (
    id                      UUID        NOT NULL DEFAULT gen_random_uuid(),
    user_id                 UUID        NOT NULL,
    stripe_subscription_id  TEXT        NOT NULL,
    stripe_price_id         TEXT        NOT NULL,
    status                  TEXT        NOT NULL,
    current_period_start    TIMESTAMPTZ,
    current_period_end      TIMESTAMPTZ,
    cancel_at_period_end    BOOLEAN     NOT NULL DEFAULT FALSE,
    created_at              TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at              TIMESTAMPTZ NOT NULL DEFAULT now(),

    CONSTRAINT subscriptions_pk         PRIMARY KEY (id),
    CONSTRAINT subscriptions_user_fk    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT subscriptions_stripe_uq  UNIQUE (stripe_subscription_id)
);

CREATE INDEX idx_subscriptions_user   ON subscriptions (user_id);
CREATE INDEX idx_subscriptions_stripe ON subscriptions (stripe_subscription_id);

-- ============================================================
-- SESSIONS
-- ============================================================

CREATE TABLE sessions (
    id          UUID        NOT NULL DEFAULT gen_random_uuid(),
    user_id     UUID        NOT NULL,
    created_at  TIMESTAMPTZ NOT NULL DEFAULT now(),
    expires_at  TIMESTAMPTZ NOT NULL,

    CONSTRAINT sessions_pk      PRIMARY KEY (id),
    CONSTRAINT sessions_user_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE INDEX idx_sessions_user    ON sessions (user_id);
CREATE INDEX idx_sessions_expires ON sessions (expires_at);

-- ============================================================
-- HELPER: auto-update updated_at on row change
-- ============================================================

CREATE OR REPLACE FUNCTION set_updated_at()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = now();
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trg_users_updated_at
    BEFORE UPDATE ON users
    FOR EACH ROW EXECUTE FUNCTION set_updated_at();

CREATE TRIGGER trg_subscriptions_updated_at
    BEFORE UPDATE ON subscriptions
    FOR EACH ROW EXECUTE FUNCTION set_updated_at();
