<?php
namespace Bga\Games\BagOfChips;

trait DebugUtilTrait {

//////////////////////////////////////////////////////////////////////////////
//////////// Utility functions
////////////

    function debugSetup() {
        if ($this->getBgaEnvironment() != 'studio') { 
            return;
        } 

        //$this->debugSetPlayerScore(2343492, 10);
        //$this->debugSetScore(39);
        //$this->debugSetReputation(8);

        //$this->debugAddChips(2343492, 'A', 15);
        //$this->debugAddChips(2343492, 'B', 10);

        //$this->cards->pickCardsForLocation(13, 'deck', 'void');
        
        //$this->debugLastTurn();
    }

    function debugSetScore($score) {
		$this->DbQuery("UPDATE player SET `player_score` = $score");
    }
    
    function debugSetPlayerScore($playerId, $score) {
		$this->DbQuery("UPDATE player SET `player_score` = $score WHERE player_id = $playerId");
    }

    function debugSetReputation($score) {
		$this->DbQuery("UPDATE player SET `player_reputation` = $score");
    }
    
    function debugSetPlayerReputation($playerId, $score) {
		$this->DbQuery("UPDATE player SET `player_reputation` = $score WHERE player_id = $playerId");
    }

    function debugLastTurn() {
        $this->setGameStateValue(LAST_TURN, 1);
    }
    
    function debugEmpty() {
		$this->cards->moveAllCardsInLocation('deck', 'void');
        $this->cards->moveAllCardsInLocation('discard', 'void');
    }

    function debugAddChips($playerId, $letter, $number) {
        for ($i = 0; $i < $number; $i++) {
            $chipIndex = intval($this->chips->countCardInLocation('played'.$playerId));
            $this->chips->pickCardForLocation('deck'.$letter, 'played'.$playerId, $chipIndex);
        }
    }

    public function debug_playToEnd() {
        $this->debug_playAutomatically(999999);
    }

    public function debug_playAutomatically(int $moves = 50) {
        $count = 0;
        while (intval($this->gamestate->getCurrentMainStateId()) < 99 && $count < $moves) {
            $count++;
            foreach($this->gamestate->getActivePlayerList() as $playerId) {
                $playerId = (int)$playerId;
                $this->gamestate->runStateClassZombie($this->gamestate->getCurrentState($playerId), $playerId);
            }
        }
    }

    function debug($debugData) {
        if ($this->getBgaEnvironment() != 'studio') { 
            return;
        }die('debug data : '.json_encode($debugData));
    }
}
