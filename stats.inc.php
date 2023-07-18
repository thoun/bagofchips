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
 * stats.inc.php
 *
 * BagOfChips game statistics description
 *
 */

/*
    In this file, you are describing game statistics, that will be displayed at the end of the
    game.
    
    !! After modifying this file, you must use "Reload  statistics configuration" in BGA Studio backoffice
    ("Control Panel" / "Manage Game" / "Your Game")
    
    There are 2 types of statistics:
    _ table statistics, that are not associated to a specific player (ie: 1 value for each game).
    _ player statistics, that are associated to each players (ie: 1 value for each player in the game).

    Statistics types can be "int" for integer, "float" for floating point values, and "bool" for boolean
    
    Once you defined your statistics there, you can start using "initStat", "setStat" and "incStat" method
    in your game logic, using statistics names defined below.
    
    !! It is not a good idea to modify this file when a game is running !!

    If your game is already public on BGA, please read the following before any change:
    http://en.doc.boardgamearena.com/Post-release_phase#Changes_that_breaks_the_games_in_progress
    
    Notes:
    * Statistic index is the reference used in setStat/incStat/initStat PHP method
    * Statistic index must contains alphanumerical characters and no space. Example: 'turn_played'
    * Statistics IDs must be >=10
    * Two table statistics can't share the same ID, two player statistics can't share the same ID
    * A table statistic can have the same ID than a player statistics
    * Statistics ID is the reference used by BGA website. If you change the ID, you lost all historical statistic data. Do NOT re-use an ID of a deleted statistic
    * Statistic name is the English description of the statistic as shown to players
    
*/

$commonStats = [
    // objectives
    "validatedMinusObjective" => [
        "id" => 20,
        "name" => totranslate("Validated [-] objectives"),
        "type" => "int"
    ],
    "validatedPlusObjective" => [
        "id" => 21,
        "name" => totranslate("Validated [+] objectives"),
        "type" => "int"
    ],
    "avgValidatedMinusObjective" => [
        "id" => 22,
        "name" => totranslate("Validated [-] objectives (average per round)"),
        "type" => "float"
    ],
    "avgValidatedPlusObjective" => [
        "id" => 23,
        "name" => totranslate("Validated [+] objectives (average per round)"),
        "type" => "float"
    ],

    // points
    "pointsMinusObjectives" => [
        "id" => 30,
        "name" => totranslate("Points lost with [-] objectives"),
        "type" => "int"
    ],
    "pointsPlusObjectives" => [
        "id" => 31,
        "name" => totranslate("Points gained with [+] objectives"),
        "type" => "int"
    ],
    "avgPointsMinusObjectives" => [
        "id" => 32,
        "name" => totranslate("Points lost with [-] objectives (average per round)"),
        "type" => "float"
    ],
    "avgPointsPlusObjectives" => [
        "id" => 33,
        "name" => totranslate("Points gained with [+] objectives (average per round)"),
        "type" => "float"
    ],

    // rewards
    "rewards" => [
        "id" => 40,
        "name" => totranslate("Rewards"),
        "type" => "int"
    ],
    "avgRewardsPerRound" => [
        "id" => 41,
        "name" => totranslate("Rewards (average per round)"),
        "type" => "float"
    ],

    // special objective
    "specialObjectiveWin" => [
        "id" => 50,
        "name" => totranslate("Game won with special objective"),
        "type" => "bool"
    ],
    "specialObjectiveLoss" => [
        "id" => 51,
        "name" => totranslate("Rounds lost with special objective"),
        "type" => "int"
    ],
];

$stats_type = [
    // Statistics global to table
    "table" => $commonStats + [
        "roundNumber" => [
            "id" => 10,
            "name" => totranslate("Number of rounds"),
            "type" => "int"
        ],
    ],
    
    // Statistics existing for each player
    "player" => $commonStats + [
    ],
];
