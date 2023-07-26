<?php

/**
 *------
 * BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
 * BagOfChips implementation : © <Your name here> <Your email address here>
 *
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 *
 * gameoptions.inc.php
 *
 * BagOfChips game options description
 * 
 * In this file, you can define your game options (= game variants).
 *   
 * Note: If your game has no variant, you don't have to modify this file.
 *
 * Note²: All options defined in this file should have a corresponding "game state labels"
 *        with the same ID (see "initGameStateLabels" in bagofchips.game.php)
 *
 * !! It is not a good idea to modify this file when a game is running !!
 *
 */

require_once("modules/php/constants.inc.php");

$game_options = [];
   
$game_preferences = [ 
    202 => [
        'name' => totranslate('Skin'),
        'needReload' => false,
        'values' => [
            0 => ['name' => totranslate('Automatic')],
            1 => ['name' => totranslate('Default - Bag of chips')],
            2 => ['name' => totranslate('DE - ‘ne tutte chips')],
            3 => ['name' => totranslate('CA - Sac de Chips')],
        ],
        'default' => 0
    ],
    ];