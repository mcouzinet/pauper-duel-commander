import { defineCollection, z } from 'astro:content';
import { glob } from 'astro/loaders';

const tournaments = defineCollection({
  loader: glob({ pattern: '**/*.json', base: './content/tournaments' }),
  schema: z.object({
    title: z.string(),
    date: z.string().regex(/^\d{4}-\d{2}-\d{2}$/),
    location: z.string(),
    city: z.string(),
    playerCount: z.number().min(2),
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
    cards: z.string(),
  }),
});

export const collections = { tournaments, decklists };
