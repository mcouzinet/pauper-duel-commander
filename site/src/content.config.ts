import { defineCollection, z } from 'astro:content';
import { glob } from 'astro/loaders';

const tournaments = defineCollection({
  loader: glob({ pattern: '**/*.json', base: './content/tournaments' }),
  schema: z.object({
    title: z.string(),
    date: z.string().regex(/^\d{4}-\d{2}-\d{2}$/),
    location: z.string(),
    city: z.string(),
    // 0 = capacity not announced yet; every display guards on `> 0`.
    playerCount: z.number().min(0).default(0),
    actualPlayerCount: z.number().nullable().optional(),
    signupUrl: z.string().optional(),
    details: z.string().optional(),
    top8: z.array(z.object({
      place: z.number().min(1).max(8),
      playerName: z.string(),
      commanderName: z.string(),
      score: z.string(),
      decklistSlug: z.string().nullable(),
    })).max(8).default([]),
    metaList: z.array(z.object({
      name: z.string(),
      count: z.number().min(1),
    })).default([]),
  }),
});

const decklists = defineCollection({
  loader: glob({ pattern: '**/*.json', base: './content/decklists' }),
  schema: z.object({
    title: z.string(),
    commander: z.string(),
    partner: z.string().optional(),
    date: z.string().regex(/^\d{4}-\d{2}-\d{2}$/).optional(),
    author: z.string().optional(),
    archetype: z.string().optional(),
    // Editorial labels, used to build the discovery shelves on the index page.
    // Known value: "debutant" — a list the team recommends as a first deck.
    tags: z.array(z.string()).default([]),
    cards: z.string(),
  }),
});

// Ban list announcement history — one file per official announcement.
const banlistHistory = defineCollection({
  loader: glob({ pattern: '**/*.json', base: './content/banlist-history' }),
  schema: z.object({
    date: z.string().regex(/^\d{4}-\d{2}-\d{2}$/),
    source: z.string(),
    kind: z.enum(['initial', 'update']).default('update'),
    changes: z.array(z.object({
      card: z.string(),
      type: z.enum(['banned', 'unbanned', 'restricted']),
      experimental: z.boolean().default(false),
    })).default([]),
    // Reasoning paragraphs, bilingual so both /fr/ and /en/ render natively.
    notes: z.array(z.object({
      fr: z.string(),
      en: z.string(),
    })).default([]),
  }),
});

export const collections = { tournaments, decklists, banlistHistory };
