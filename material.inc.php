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
 * material.inc.php
 *
 * BagOfChips game material description
 *
 * Here, you can describe the material of your game with PHP variables.
 *   
 * This file is loaded in your game logic class constructor, ie these variables
 * are available everywhere in your game logic code.
 *
 */

require_once(__DIR__.'/modules/php/constants.inc.php');
require_once(__DIR__.'/modules/php/objects/card.php');

$this->CARDS = [
    1 => [ // one of each
        1 => new CardType(1, [1]),
        2 => new CardType(42, [2]),
    ],
    2 => [ // min colors
        1 => new CardType(4, [YELLOW => 3, GREEN => 3]),
        2 => new CardType(9, [RED => 2, GREEN => 3]),
        3 => new CardType(16, [PURPLE => 2, RED => 2]),
        4 => new CardType(120, [ORANGE => 3]),
        5 => new CardType(140, [PURPLE => 4]),
        6 => new CardType(160, [RED => 5]),
        7 => new CardType(180, [GREEN => 6]),
        8 => new CardType(200, [YELLOW => 7]),
    ],
    3 => [ // color A = color B
        1 => new CardType(25, [GREEN, YELLOW]),
        2 => new CardType(30, [RED, GREEN]),
        3 => new CardType(35, [PURPLE, RED]),
        4 => new CardType(40, [ORANGE, PURPLE]),
        5 => new CardType(70, [PURPLE, GREEN]),
        6 => new CardType(100, [PURPLE, YELLOW]),
        7 => new CardType(110, [ORANGE, GREEN]),
    ],
    
    4 => [ // last chip color
        1 => new CardType(61, [YELLOW]),
        2 => new CardType(71, [GREEN]),
        3 => new CardType(81, [RED]),
        4 => new CardType(91, [PURPLE]),
        5 => new CardType(111, [ORANGE]),
    ],
    
    5 => [ // no color
        1 => new CardType(201, [ORANGE]),
        2 => new CardType(202, [PURPLE]),
    ],
    
    6 => [ // points / color
        1 => new CardType(5, [YELLOW]),
        2 => new CardType(8, [GREEN]),
        3 => new CardType(11, [RED]),
        4 => new CardType(15, [PURPLE]),
        5 => new CardType(22, [ORANGE]),
    ],

    7 => [ // more A than B
        1 => new CardType(45, [GREEN, YELLOW]),
        2 => new CardType(50, [RED, GREEN]),
        3 => new CardType(55, [PURPLE, RED]),
        4 => new CardType(60, [ORANGE, PURPLE]),
        5 => new CardType(80, [RED, YELLOW]),
        6 => new CardType(90, [ORANGE, RED]),
        7 => new CardType(99999, [ORANGE, YELLOW]),
    ],
];