<?php
/**
 * Tournament ACF Field Group
 *
 * Registers all ACF fields for the Tournament custom post type.
 * Fields are registered programmatically so they don't require ACF JSON sync.
 *
 * @package PDC_Theme
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('acf/init', function() {
    if (!function_exists('acf_add_local_field_group')) {
        return;
    }

    acf_add_local_field_group(array(
        'key'                   => 'group_tournament',
        'title'                 => 'Informations du tournoi',
        'fields'                => array(

            // ----------------------------------------------------------------
            // Tournament info
            // ----------------------------------------------------------------

            array(
                'key'           => 'field_tournament_date',
                'label'         => 'Date',
                'name'          => 'tournament_date',
                'type'          => 'date_picker',
                'display_format'=> 'd/m/Y',
                'return_format' => 'Ymd',
                'required'      => 1,
            ),
            array(
                'key'           => 'field_tournament_location',
                'label'         => 'Boutique / Lieu',
                'name'          => 'tournament_location',
                'type'          => 'text',
                'required'      => 1,
            ),
            array(
                'key'           => 'field_tournament_city',
                'label'         => 'Ville',
                'name'          => 'tournament_city',
                'type'          => 'text',
                'required'      => 1,
            ),
            array(
                'key'           => 'field_tournament_player_count',
                'label'         => 'Nombre de participants',
                'name'          => 'tournament_player_count',
                'type'          => 'number',
                'min'           => 2,
                'required'      => 1,
            ),
            array(
                'key'           => 'field_tournament_signup_url',
                'label'         => "Lien d'inscription (optionnel)",
                'name'          => 'tournament_signup_url',
                'type'          => 'url',
                'required'      => 0,
            ),
            array(
                'key'           => 'field_tournament_details',
                'label'         => 'Informations complémentaires',
                'instructions'  => 'Infos libres : horaire, PAF, rondes, récompenses, ambiance… Les retours à la ligne sont conservés.',
                'name'          => 'tournament_details',
                'type'          => 'textarea',
                'rows'          => 10,
                'new_lines'     => 'wpautop',
                'required'      => 0,
            ),

            // ----------------------------------------------------------------
            // Top 8
            // ----------------------------------------------------------------

            array(
                'key'           => 'field_tournament_top8',
                'label'         => 'Top 8',
                'name'          => 'top8',
                'type'          => 'repeater',
                'max'           => 8,
                'layout'        => 'table',
                'button_label'  => 'Ajouter une entrée',
                'sub_fields'    => array(
                    array(
                        'key'          => 'field_top8_place',
                        'label'        => 'Place',
                        'name'         => 'place',
                        'type'         => 'number',
                        'min'          => 1,
                        'max'          => 8,
                        'required'     => 1,
                        'column_width' => '8',
                    ),
                    array(
                        'key'          => 'field_top8_player_name',
                        'label'        => 'Joueur',
                        'name'         => 'player_name',
                        'type'         => 'text',
                        'required'     => 1,
                        'column_width' => '22',
                    ),
                    array(
                        'key'          => 'field_top8_commander_name',
                        'label'        => 'Général (nom Scryfall exact)',
                        'name'         => 'commander_name',
                        'type'         => 'text',
                        'required'     => 1,
                        'placeholder'  => 'Ex: Isamaru, Hound of Konda',
                        'column_width' => '35',
                    ),
                    array(
                        'key'          => 'field_top8_score',
                        'label'        => 'Score',
                        'name'         => 'score',
                        'type'         => 'text',
                        'placeholder'  => 'Ex: 5-2',
                        'column_width' => '15',
                    ),
                    array(
                        'key'          => 'field_top8_decklist_post',
                        'label'        => 'Decklist (optionnel)',
                        'name'         => 'decklist_post',
                        'type'         => 'post_object',
                        'post_type'    => array('decklist'),
                        'allow_null'   => 1,
                        'return_format'=> 'id',
                        'column_width' => '20',
                    ),
                ),
            ),

            // ----------------------------------------------------------------
            // Participants (for meta stats)
            // ----------------------------------------------------------------

            array(
                'key'           => 'field_tournament_meta_list',
                'label'         => 'Méta (généraux joués)',
                'instructions'  => 'Collez la liste des généraux joués, un par ligne. Nombre optionnel devant (défaut = 1).<br>Pour les partners/backgrounds, séparez avec <code>//</code> ou <code>/</code>.<br>Exemples :<br><code>2 Strix</code> → 2 joueurs avec Strix<br><code>Arabella</code> → 1 joueur avec Arabella<br><code>Gut, True Soul Zealot // Agent of the Iron Throne</code> → partner + background',
                'name'          => 'tournament_meta_list',
                'type'          => 'textarea',
                'rows'          => 15,
                'new_lines'     => '',
                'required'      => 0,
                'placeholder'   => "1 Arabella\n2 Strix\nCormella Baleful\nGut, True Soul Zealot // Agent of the Iron Throne",
            ),
        ),
        'location' => array(
            array(
                array(
                    'param'    => 'post_type',
                    'operator' => '==',
                    'value'    => 'tournament',
                ),
            ),
        ),
        'menu_order'           => 0,
        'position'             => 'normal',
        'style'                => 'default',
        'label_placement'      => 'top',
        'instruction_placement'=> 'label',
    ));
});
