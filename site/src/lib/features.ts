/**
 * Build-time feature flags (from PUBLIC_* env vars, inlined at build).
 *
 * Submissions stay hidden until PUBLIC_SUBMISSIONS_ENABLED === 'true', so the
 * backend + forms can ship (and deploy) before the server secrets are placed,
 * without exposing an incomplete flow. Flip the flag once setup is done.
 */
export const submissionsEnabled = import.meta.env.PUBLIC_SUBMISSIONS_ENABLED === 'true';
