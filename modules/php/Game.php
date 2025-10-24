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
  * bagofchips.game.php
  *
  * This is the main file for your game logic.
  *
  * In this PHP file, you are going to defines the rules of the game.
  *
  */
namespace Bga\Games\BagOfChips;

use Bga\GameFramework\Components\Deck;
use Bga\GameFramework\Table;
use Bga\Games\BagOfChips\States\StartRound;
use Card;
use CardType;
use Chip;

require_once('Objects/card.php');
require_once('Objects/chip.php');
require_once('Objects/player.php');
require_once('Objects/undo.php');
require_once('constants.inc.php');

class Game extends Table {
    use DebugUtilTrait;

    public Deck $cards;
    public Deck $chips;
    public array $CARDS;

	function __construct() {
        // Your global variables labels:
        //  Here, you can assign labels to global variables you are using for this game.
        //  You can use any number of global variables with IDs between 10 and 99.
        //  If your game has options (variants), you also have to associate here a label to
        //  the corresponding ID in gameoptions.inc.php.
        // Note: afterwards, you can get/set the global variables with getGameStateValue/setGameStateInitialValue/setGameStateValue
        parent::__construct();
        
        self::initGameStateLabels([]);   
		
        $this->cards = $this->deckFactory->createDeck("card");
        $this->cards->autoreshuffle = true;     
		
        $this->chips = $this->deckFactory->createDeck("chip");
        $this->chips->autoreshuffle = false;   

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
	}

    /*
        setupNewGame:
        
        This method is called only once, when a new game is launched.
        In this method, you must setup the game according to the game rules, so that
        the game is ready to be played.
    */
    protected function setupNewGame( $players, $options = []) {    
        // Set the colors of the players with HTML color code
        // The default below is red/green/blue/orange/brown
        // The number of colors defined here must correspond to the maximum number of players allowed for the gams
        $gameinfos = self::getGameinfos();
        $default_colors = $gameinfos['player_colors'];
 
        // Create players
        // Note: if you added some extra field on "player" table in the database (dbmodel.sql), you can initialize it there.
        $sql = "INSERT INTO player (player_id, player_color, player_canal, player_name, player_avatar) VALUES ";
        $values = [];

        foreach( $players as $player_id => $player ) {
            $color = array_shift( $default_colors );

            $values[] = "('".$player_id."','$color','".$player['player_canal']."','".addslashes( $player['player_name'] )."','".addslashes( $player['player_avatar'] )."')";
        }
        $sql .= implode(',', $values);
        self::DbQuery( $sql );
        self::reattributeColorsBasedOnPreferences( $players, $gameinfos['player_colors'] );
        self::reloadPlayersBasicInfos();
        
        /************ Start the game initialization *****/

        // Init global values with their initial values
        
        // Init game statistics
        // (note: statistics used in this file must be defined in your stats.inc.php file)
        $this->initStat('table', 'roundNumber', 0);
        foreach(['table', 'player'] as $type) {
            foreach([                      
                // objectives
                "validatedMinusObjective", "validatedPlusObjective", "avgValidatedMinusObjective", "avgValidatedPlusObjective",
                // points
                "pointsMinusObjectives", "pointsPlusObjectives", "avgPointsMinusObjectives", "avgPointsPlusObjectives",
                // rewards
                "rewards", "avgRewardsPerRound",
                // special objective
                "specialObjectiveWin", "specialObjectiveLoss",
            ] as $name) {
                $this->initStat($type, $name, 0);
            }
        }

        // setup the initial game situation here
        $this->setupCards();
        $this->setupChips();

        /************ End of the game initialization *****/
        return StartRound::class;
    }

    /*
        getAllDatas: 
        
        Gather all informations about current game situation (visible by the current player).
        
        The method is called each time the game interface is displayed to a player, ie:
        _ when the game starts
        _ when a player refreshes the game page (F5)
    */
    protected function getAllDatas(): array {
        $result = [];
    
        $currentPlayerId = intval(self::getCurrentPlayerId());    // !! We must only return informations visible by this player !!
    
        // Get information about players
        // Note: you can retrieve some extra field you added for "player" table in "dbmodel.sql" if you need it.
        $sql = "SELECT player_id id, player_no playerNo, player_rewards rewards FROM player ";
        $result['players'] = self::getCollectionFromDb( $sql );
  
        // Gather all information about current game situation (visible by player $current_player_id).
        
        foreach($result['players'] as $playerId => &$player) {
            $player['playerNo'] = intval($player['playerNo']);

            $player['rewards'] = intval($player['rewards']);
            
            $player['discard'] = Card::onlyIds($this->getCardsByLocation('discard', $playerId));

            $stateId = $this->gamestate->getCurrentMainStateId();
            $hidePlusMinus = $stateId == ST_MULTIPLAYER_PLACE_CARDS && $currentPlayerId != $playerId;
            $player['minus'] = $hidePlusMinus ? [] : $this->getCardsByLocation('minus', $playerId);
            $player['plus'] = $hidePlusMinus ? [] : $this->getCardsByLocation('plus', $playerId);

            if ($currentPlayerId == $playerId) {
                $player['hand'] = $this->getCardsByLocation('hand', $playerId);
            }
        }

        $result['chips'] = $this->getChipsByLocation('table');
        $result['roundResult'] = $this->getGlobalVariable(ROUND_RESULT, true);
  
        return $result;
    }

    /*
        getGameProgression:
        
        Compute and return the current game progression.
        The number returned must be an integer beween 0 (=the game just started) and
        100 (= the game is finished or almost finished).
    
        This method is called each time we are in a game state with the "updateGameProgression" property set to true 
        (see states.inc.php)
    */
    function getGameProgression() {
        $maxScore = $this->getMaxPlayerTokens();
        return $maxScore * 100 / $this->getTokensToWin();
    }

    function setGlobalVariable(string $name, /*object|array*/ $obj) {
        /*if ($obj == null) {
            throw new \Error('Global Variable null');
        }*/
        $jsonObj = json_encode($obj);
        $this->DbQuery("INSERT INTO `global_variables`(`name`, `value`)  VALUES ('$name', '$jsonObj') ON DUPLICATE KEY UPDATE `value` = '$jsonObj'");
    }

    function getGlobalVariable(string $name, $asArray = null) {
        $json_obj = $this->getUniqueValueFromDB("SELECT `value` FROM `global_variables` where `name` = '$name'");
        if ($json_obj) {
            $object = json_decode($json_obj, $asArray);
            return $object;
        } else {
            return null;
        }
    }

    function deleteGlobalVariable(string $name) {
        $this->DbQuery("DELETE FROM `global_variables` where `name` = '$name'");
    }

    function deleteGlobalVariables(array $names) {
        $this->DbQuery("DELETE FROM `global_variables` where `name` in (".implode(',', array_map(fn($name) => "'$name'", $names)).")");
    }

    function getPlayersIds() {
        return array_keys($this->loadPlayersBasicInfos());
    }

    function getPlayerRewards(int $playerId) {
        return intval($this->getUniqueValueFromDB("SELECT player_rewards FROM player WHERE player_id = $playerId"));
    }

    function incPlayerRewards(int $playerId, int $amount, $message = '', $args = []) {
        if ($amount != 0) {
            $this->DbQuery("UPDATE player SET `player_rewards` = `player_rewards` + $amount WHERE player_id = $playerId");
        }
            
        $this->notify->all('rewards', $message, [
            'playerId' => $playerId,
            'player_name' => $this->getPlayerNameById($playerId),
            'newScore' => $this->getPlayerRewards($playerId),
            'incScore' => $amount,
        ] + $args);
    }

    function getCardFromDb(/*array|null*/ $dbCard) {
        if ($dbCard == null) {
            return null;
        }
        return new Card($dbCard, $this->CARDS);
    }

    function getCardsFromDb(array $dbCards) {
        return array_map(fn($dbCard) => $this->getCardFromDb($dbCard), array_values($dbCards));
    }

    function getCardById(int $id) {
        $sql = "SELECT * FROM `card` WHERE `card_id` = $id";
        $dbResults = $this->getCollectionFromDb($sql);
        $cards = array_map(fn($dbCard) => $this->getCardFromDb($dbCard), array_values($dbResults));
        return count($cards) > 0 ? $cards[0] : null;
    }

    function getCardsByLocation(string $location, /*int|null*/ $location_arg = null) {
        $sql = "SELECT * FROM `card` WHERE `card_location` = '$location'";
        if ($location_arg !== null) {
            $sql .= " AND `card_location_arg` = $location_arg";
        }
        $sql .= " ORDER BY `card_location_arg`";
        $dbResults = $this->getCollectionFromDb($sql);
        return array_map(fn($dbCard) => $this->getCardFromDb($dbCard), array_values($dbResults));
    }

    function setupCards() {
        $cards = [];
        foreach ($this->CARDS as $type => $cardsOfType) {
            foreach ($cardsOfType as $subTtype => $cardsType) {
                $cards[] = [ 'type' => $type, 'type_arg' => $subTtype, 'nbr' => 1 ];
            }
        }
        $this->cards->createCards($cards, 'deck');
        $this->cards->shuffle('deck');
    }

    function getChipFromDb(/*array|null*/ $dbCard) {
        if ($dbCard == null) {
            return null;
        }
        return new Chip($dbCard);
    }

    function getChipsFromDb(array $dbCards) {
        return array_map(fn($dbCard) => $this->getChipFromDb($dbCard), array_values($dbCards));
    }

    function getChipsByLocation(string $location, /*int|null*/ $location_arg = null) {
        $sql = "SELECT * FROM `chip` WHERE `card_location` = '$location'";
        if ($location_arg !== null) {
            $sql .= " AND `card_location_arg` = $location_arg";
        }
        $sql .= " ORDER BY `card_location_arg`";
        $dbResults = $this->getCollectionFromDb($sql);
        return array_map(fn($dbCard) => $this->getChipFromDb($dbCard), array_values($dbResults));
    }

    function setupChips() {
        $cards = [];
        for ($i = 1; $i <= 5; $i++) {
            $cards[] = [ 'type' => $i, 'type_arg' => null, 'nbr' => $i + 2 ];
        }
        $this->chips->createCards($cards, 'bag');
        $this->chips->shuffle('bag');
    }
    
    function getPhase() {
        return $this->getGlobalVariable(PHASE);
    }

    function getMaxPlayerTokens() {
        return intval($this->getUniqueValueFromDB("SELECT max(player_rewards) FROM player"));
    }

    function getTokensToWin() {
        return count($this->getPlayersIds()) == 2 ? 3 : 4;
    }

    function isCardScored(Card $card, array $counts, Chip $lastChip) {
        switch ($card->type) {
            case 1:
                return array_all($counts, fn($count) => $count >= $card->params[0]);
            case 2:
                foreach ($card->params as $color => $min) {
                    if ($counts[$color] < $min) {
                        return false;
                    }
                }
                return true;
            case 3:
                return $counts[$card->params[0]] == $counts[$card->params[1]];
            case 4:
                return $lastChip->color == $card->params[0];
            case 5:
                return $counts[$card->params[0]] == 0;
            case 6:
                return $counts[$card->params[0]] > 0;
            case 7:
                return $counts[$card->params[0]] > $counts[$card->params[1]];
        }
    }
    
    function scoreRound() {
        $chips = $this->getChipsByLocation('table');
        $counts = [];
        for ($i = 1; $i <= 5; $i++) {
            $counts[$i] = count(array_filter($chips, fn($chip) => $chip->color == $i));
        }
        $lastChip = array_find($chips, fn($chip) => $chip->locationArg == 5);
        
        $playersIds = $this->getPlayersIds();

        $result = [
            'instantWinner' => null,
            'gameWon' => false,
            'cards' => [], // $id => [playerId, side, score, card points]
        ];

        foreach($playersIds as $playerId) {
            $minus = $this->getCardsByLocation('minus', $playerId);
            $plus = $this->getCardsByLocation('plus', $playerId);

            $roundLost = false;
            $roundScore = 0;
            
            foreach ($minus as $card) {
                $scored = $this->isCardScored($card, $counts, $lastChip);
                $points = $card->type == 6 ? $card->points * $counts[$card->params[0]] : $card->points;
                $message = null;
                if ($scored) {
                    $this->DbQuery("UPDATE player SET player_round_score = player_round_score - $points, player_round_score_minus = player_round_score_minus + $points WHERE player_id = $playerId");
                    $message = $points == 99999 ?
                        clienttranslate('${player_name} scores the [-] card and loses the round! ${card_image}') :
                        clienttranslate('${player_name} scores the [-] card and loses ${points} points ${card_image}');

                    $this->incStat(1, 'validatedMinusObjective');
                    $this->incStat(1, 'validatedMinusObjective', $playerId);
                    if ($points == 99999) {
                        $this->incStat(1, 'specialObjectiveLoss');
                        $roundLost = true;
                    } else {
                        $roundScore -= $points;
                        $this->incStat($points, 'pointsMinusObjectives');
                        $this->incStat($points, 'pointsMinusObjectives', $playerId);
                    }
                } else {
                    $message = clienttranslate('${player_name} doesn\'t score the [-] card ${card_image}');
                }
                
                $score = $scored ? -$points : 0;
                $this->notify->all('scoreCard', $message, [
                    'playerId' => $playerId,
                    'player_name' => $this->getPlayerNameById($playerId),
                    'card' => $card,
                    'card_image' => '',
                    'preserve' => ['card'],
                    'score' => $score,
                    'points' => $score, // for log
                    'side' => 'minus',
                ]);

                $result['cards'][$card->id] = [$playerId, 'minus', $score, $card->points];
            }
            
            if (!$roundLost) {
                foreach ($plus as $card) {
                    $scored = $this->isCardScored($card, $counts, $lastChip);
                    $points = $card->type == 6 ? $card->points * $counts[$card->params[0]] : $card->points;
                    if ($scored) {
                        $this->DbQuery("UPDATE player SET player_round_score = player_round_score + $points, player_round_score_plus = player_round_score_plus + $points WHERE player_id = $playerId");

                        if ($points == 99999) {
                            $result['instantWinner'] = $playerId;
                            $result['gameWon'] = true;
                        }
                        
                        $message = $points == 99999 ?
                            clienttranslate('${player_name} scores the [+] card and wins the game! ${card_image}') :
                            clienttranslate('${player_name} scores the [+] card and gains ${points} points ${card_image}');

                        $this->incStat(1, 'validatedPlusObjective');
                        $this->incStat(1, 'validatedPlusObjective', $playerId);
                        if ($points == 99999) {
                            $this->setStat(1, 'specialObjectiveWin');
                        } else {
                            $roundScore += $points;
                            $this->incStat($points, 'pointsPlusObjectives');
                            $this->incStat($points, 'pointsPlusObjectives', $playerId);
                        }
                    } else {
                        $message = clienttranslate('${player_name} doesn\'t score the [+] card ${card_image}');
                    }
                    
                $score = $scored ? $points : 0;
                    $this->notify->all('scoreCard', $message, [
                        'playerId' => $playerId,
                        'player_name' => $this->getPlayerNameById($playerId),
                        'card' => $card,
                        'card_image' => '',
                        'preserve' => ['card'],
                        'score' => $score,
                        'points' => $score, // for log
                        'side' => 'plus',
                    ]);

                    $result['cards'][$card->id] = [$playerId, 'plus', $score, $card->points];
                }
            }
            
            if ($result['gameWon']) {
                break;
            }

            if (!$roundLost && !$result['gameWon']) {
                $this->notify->all('log', clienttranslate('${player_name} scores ${number} points this round'), [
                    'playerId' => $playerId,
                    'player_name' => $this->getPlayerNameById($playerId),
                    'number' => $roundScore, // for log
                ]);
            }
        }

        $this->setGlobalVariable(ROUND_RESULT, $result);

        return $result;
    }
    
///////////////////////////////////////////////////////////////////////////////////:
////////// DB upgrade
//////////

    /*
        upgradeTableDb:
        
        You don't have to care about this until your game has been published on BGA.
        Once your game is on BGA, this method is called everytime the system detects a game running with your old
        Database scheme.
        In this case, if you change your Database scheme, you just have to apply the needed changes in order to
        update the game database and allow the game to continue to run with your new version.
    
    */
    
    function upgradeTableDb($from_version) {
        // $from_version is the current version of this game database, in numerical form.
        // For example, if the game was running with a release of your game named "140430-1345",
        // $from_version is equal to 1404301345

        /*if ($from_version <= 2305241900) {
            // ! important ! Use DBPREFIX_<table_name> for all tables
            self::applyDbUpgradeToAllDB("ALTER TABLE DBPREFIX_player CHANGE COLUMN `player_fame` `player_reputation` tinyint UNSIGNED NOT NULL DEFAULT 0");
        }*/
    }    
}
