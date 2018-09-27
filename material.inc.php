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
 * material.inc.php
 *
 * BriscolaSuperamici game material description
 *
 * Here, you can describe the material of your game with PHP variables.
 *   
 * This file is loaded in your game logic class constructor, ie these variables
 * are available everywhere in your game logic code.
 *
 */

$this->italianColors = array(
    1 => array(
        'name' => clienttranslate('sword'),
        'nametr' => self::_('sword'),
        'nameorig' => 'sword'),
    2 => array(
        'name' => clienttranslate('cup'),
        'nametr' => self::_('cup'),
        'nameorig' => 'cup'),
    3 => array(
        'name' => clienttranslate('club'),
        'nametr' => self::_('club'),
        'nameorig' => 'club'),
    4 => array(
        'name' => clienttranslate('coin'),
        'nametr' => self::_('coin'),
        'nameorig' => 'coin')
);

$this->frenchColors = array(
    1 => array(
        'name' => clienttranslate('spade'),
        'nametr' => self::_('spade')),
    2 => array(
        'name' => clienttranslate('heart'),
        'nametr' => self::_('heart')),
    3 => array(
        'name' => clienttranslate('club'),
        'nametr' => self::_('club')),
    4 => array(
        'name' => clienttranslate('diamond'),
        'nametr' => self::_('diamond'))
);

$this->frenchIcons = array(
    1 => '<span style="color: black;" class="french-suit-log">'.json_decode('"' . '\u2660' . '"').'</span>' , //spade
    2 => '<span style="color: red;" class="french-suit-log">'.json_decode('"' . '\u2665' . '"').'</span>' , //heart
    3 => '<span style="color: black;" class="french-suit-log">'.json_decode('"' . '\u2663' . '"').'</span>' , //club
    4 => '<span style="color: red;" class="french-suit-log">'.json_decode('"' . '\u2666' . '"').'</span>' , //diamond
);

$this->italianValueLabel = array(
    2 =>'2',
    3 => '4',
    4 => '5',
    5 => '6',
    6 => '7',
    7 => clienttranslate('Knave'),
    8 => clienttranslate('Knight'),
    9 => clienttranslate('King'),
    10 => '3',
    11 => clienttranslate('A')
);

$this->frenchValueLabel = array(
    2 =>'2',
    3 => '4',
    4 => '5',
    5 => '6',
    6 => '7',
    7 => clienttranslate('J'),
    8 => clienttranslate('Q'),
    9 => clienttranslate('K'),
    10 => '3',
    11 => clienttranslate('A')
);


