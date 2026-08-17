/**
 * Site-wide constants.
 *
 * The Discord invite was hard-coded in two places on the home page and nowhere
 * else, which is how a community format ended up with no way to reach its
 * community from six of its seven pages.
 */
export const DISCORD_URL = 'https://discord.gg/4MR2sSWdms';

/** Canonical production origin, used for absolute URLs in metadata. */
export const SITE_ORIGIN = 'https://pauperduelcommander.fr';

/** How often the committee publishes a ban list announcement. */
export const ANNOUNCEMENT_CADENCE_MONTHS = 2;
