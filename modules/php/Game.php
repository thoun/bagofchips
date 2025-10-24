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
use Bga\GameFramework\VisibleSystemException;
use Bga\Games\BagOfChips\States\StartRound;
use Card;
use CardType;

require_once('Objects/card.php');
require_once('Objects/chip.php');
require_once('Objects/player.php');
require_once('Objects/undo.php');
require_once('constants.inc.php');
require_once('utils.php');
require_once('actions.php');
require_once('states.php');
require_once('args.php');
require_once('debug-util.php');

class Game extends Table {
    use \UtilTrait;
    use \ActionTrait;
    use \StateTrait;
    use \ArgsTrait;
    use \DebugUtilTrait;

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

//////////////////////////////////////////////////////////////////////////////
//////////// Zombie
////////////

    /*
        zombieTurn:
        
        This method is called each time it is the turn of a player who has quit the game (= "zombie" player).
        You can do whatever you want in order to make sure the turn of this player ends appropriately
        (ex: pass).
        
        Important: your zombie code will be called when the player leaves the game. This action is triggered
        from the main site and propagated to the gameserver from a server, not from a browser.
        As a consequence, there is no current player associated to this action. In your zombieTurn function,
        you must _never_ use getCurrentPlayerId() or getCurrentPlayerName(), otherwise it will fail with a "Not logged" error message. 
    */

    function zombieTurn( $state, $active_player )
    {
    	$statename = $state['name'];
        if ($state['type'] === "multipleactiveplayer") {
            // Make sure player is in a non blocking status for role turn
            $this->gamestate->setPlayerNonMultiactive( $active_player, 'next');
            
            return;
        }

        throw new VisibleSystemException( "Zombie mode not supported at this game state: ".$statename );
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
