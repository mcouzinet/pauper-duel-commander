export interface ScryfallCardFace {
  name: string;
  mana_cost?: string;
  type_line: string;
  colors?: string[];
  color_identity?: string[];
  image_uris?: ScryfallImageUris;
}

export interface ScryfallImageUris {
  small: string;
  normal: string;
  large: string;
  png: string;
  border_crop: string;
  art_crop: string;
}

export interface ScryfallCard {
  object: string;
  name: string;
  mana_cost?: string;
  cmc: number;
  type_line?: string;
  colors?: string[];
  color_identity: string[];
  rarity: string;
  image_uris?: ScryfallImageUris;
  card_faces?: ScryfallCardFace[];
  legalities: Record<string, string>;
}

export type ImageSize = keyof ScryfallImageUris;
