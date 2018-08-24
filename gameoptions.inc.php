<?php

/**
 *------
 * BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
 * BriscolaSuperamici implementation : © Tommaso Pifferi <p.tommy93@gmail.com> & Antonio <ai6chr+briscola@gmail.com>
 *
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 *
 * gameoptions.inc.php
 *
 * BriscolaSuperamici game options description
 * 
 * In this file, you can define your game options (= game variants).
 *   
 * Note: If your game has no variant, you don't have to modify this file.
 *
 * Note²: All options defined in this file should have a corresponding "game state labels"
 *        with the same ID (see "initGameStateLabels" in briscolasuperamici.game.php)
 *
 * !! It is not a good idea to modify this file when a game is running !!
 *
 */

$game_options = array(

    100 => array(
        'name' => totranslate('Number of rounds'),
        'values' => array(
            1 => array('name' => totranslate('Short (1 win)')),
            2 => array('name' => totranslate('Classic (3 wins)')),
            3 => array('name' => totranslate('Long (5 wins)'))
        ),
        'default' => 2
    ),

    101 => array(
        'name' => totranslate('Team'),
        'values' => array(
            1 => array('name' => totranslate('No team (2 players) / Random teams (4 players)')),
            2 => array('name' => totranslate('By table order: 1st/3rd versus 2nd/4th (4 players only)')),
            3 => array('name' => totranslate('By table order: 1st/2nd versus 3rd/4th (4 players only)')),
            4 => array('name' => totranslate('By table order: 1st/4th versus 2nd/3rd (4 players only)')),
        ),
        'startcondition' => array(
            1 => array(),
            2 => array(
                array(
                    'type' => 'minplayers',
                    'value' => 4,
                    'message' => totranslate('The selected team setting is available for 4 players only.')
                ),
                array(
                    'type' => 'maxplayers',
                    'value' => 4,
                    'message' => totranslate('The selected team setting is available for 4 players only.')
                )
            ),
            3 => array(
                array(
                    'type' => 'minplayers',
                    'value' => 4,
                    'message' => totranslate('The selected team setting is available for 4 players only.')
                ),
                array(
                    'type' => 'maxplayers',
                    'value' => 4,
                    'message' => totranslate('The selected team setting is available for 4 players only.')
                )
            ),
            4 => array(
                array(
                    'type' => 'minplayers',
                    'value' => 4,
                    'message' => totranslate('The selected team setting is available for 4 players only.')
                ),
                array(
                    'type' => 'maxplayers',
                    'value' => 4,
                    'message' => totranslate('The selected team setting is available for 4 players only.')
                )
            )
        ),
        'default' => 1
    ),

    102=> array(
        'name' => totranslate('Deck'),
        'values' => array(
            1 => array('name' => totranslate('Italian')),
            2 => array('name' => totranslate('French'))
        ),
        'default' => 1
    )
);


