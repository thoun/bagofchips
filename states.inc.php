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
 * states.inc.php
 *
 * BagOfChips game states description
 *
 */

use Bga\GameFramework\GameStateBuilder;
use Bga\Games\BagOfChips\States\EndRound;
use Bga\Games\BagOfChips\States\EndScore;
use Bga\Games\BagOfChips\States\StartRound;

/*
   Game state machine is a tool used to facilitate game developpement by doing common stuff that can be set up
   in a very easy way from this configuration file.

   Please check the BGA Studio presentation about game state to understand this, and associated documentation.

   Summary:

   States types:
   _ activeplayer: in this type of state, we expect some action from the active player.
   _ multipleactiveplayer: in this type of state, we expect some action from multiple players (the active players)
   _ game: this is an intermediary state where we don't expect any actions from players. Your game logic must decide what is the next game state.
   _ manager: special type for initial and final state

   Arguments of game states:
   _ name: the name of the GameState, in order you can recognize it on your own code.
   _ description: the description of the current game state is always displayed in the action status bar on
                  the top of the game. Most of the time this is useless for game state with "game" type.
   _ descriptionmyturn: the description of the current game state when it's your turn.
   _ type: defines the type of game states (activeplayer / multipleactiveplayer / game / manager)
   _ action: name of the method to call when this game state become the current game state. Usually, the
             action method is prefixed by "st" (ex: "stMyGameStateName").
   _ possibleactions: array that specify possible player actions on this step. It allows you to use "checkAction"
                      method on both client side (Javacript: this.checkAction) and server side (PHP: self::checkAction).
   _ transitions: the transitions are the possible paths to go from a game state to another. You must name
                  transitions in order to use transition names in "nextState" PHP method, and use IDs to
                  specify the next game state for each transition.
   _ args: name of the method to call to retrieve arguments for this gamestate. Arguments are sent to the
           client side to be used on "onEnteringState" or to set arguments in the gamestate description.
   _ updateGameProgression: when specified, the game progression is updated (=> call to your getGameProgression
                            method).
*/
require_once("modules/php/constants.inc.php");

$basicGameStates = [
    ST_BGA_GAME_SETUP => GameStateBuilder::gameSetup(StartRound::class)->build(),
];

$playerActionsGameStates = [
    ST_MULTIPLAYER_DISCARD_CARDS => [
        "name" => "discardCards",
        "description" => clienttranslate('Waiting for other players'),
        "descriptionmyturn" => clienttranslate('${you} must discard ${number} Objective card(s)'),
        "type" => "multipleactiveplayer",
        "args" => "argDiscardCards",
        'action' => 'stMakeEveryoneActive',
        "possibleactions" => [ 
            "actDiscardCards",
        ],
        "transitions" => [
            "next" => ST_REVEAL_CHIPS,
        ],
    ],

    ST_MULTIPLAYER_PLACE_CARDS => [
        "name" => "placeCards",
        "description" => clienttranslate('Waiting for other players'),
        "descriptionmyturn" => clienttranslate('${you} must place 1 Objective card in [-] (remaining objectives will be placed on [+] side)'),
        "type" => "multipleactiveplayer",
        'action' => 'stMakeEveryoneActive',
        "possibleactions" => [ 
            "actPlaceCards",
        ],
        "transitions" => [
            "next" => ST_REVEAL_CHIPS,
        ],
    ],
];

$gameGameStates = [

    ST_REVEAL_CHIPS => [
        "name" => "revealChips",
        "description" => "",
        "type" => "game",
        "action" => "stRevealChips",
        "updateGameProgression" => true,
        "transitions" => [
            "discard" => ST_MULTIPLAYER_DISCARD_CARDS,
            "place" => ST_MULTIPLAYER_PLACE_CARDS,
            "endRound" => ST_MULTIPLAYER_BEFORE_END_ROUND,
        ],
    ],
    
    ST_MULTIPLAYER_BEFORE_END_ROUND => [
        "name" => "beforeEndRound",
        "description" => clienttranslate('Some players are seeing end round result'),
        "descriptionmyturn" => clienttranslate('End round result'),
        "type" => "multipleactiveplayer",
        "action" => "stBeforeEndRound",
        "possibleactions" => [ "actSeen" ],
        "transitions" => [
            "next" => EndRound::class, // for zombie
            "endRound" => EndRound::class,
            "endScore" => EndScore::class,
        ],
    ],
];
 
$machinestates = $basicGameStates + $playerActionsGameStates + $gameGameStates;

