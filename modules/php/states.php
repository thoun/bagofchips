<?php

trait StateTrait {

//////////////////////////////////////////////////////////////////////////////
//////////// Game state actions
////////////

    /*
        Here, you can create methods defined as "game state actions" (see "action" property in states.inc.php).
        The action method of state X is called everytime the current game state is set to X.
    */

    function stStartRound() {
        $this->setGlobalVariable(PHASE, 0);
        
        $playersIds = $this->getPlayersIds();

        foreach($playersIds as $playerId) {
            $cards = $this->getCardsFromDb($this->cards->pickCardsForLocation(6, 'deck', 'hand', $playerId));
            self::notifyPlayer($playerId, 'newHand', '', [
                'cards' => $cards,
            ]);
        }
        
        $this->gamestate->nextState('next');
    }

    private function notifRevealChips(int $slot, array $chips) {

        self::notifyAllPlayers('revealChips', clienttranslate('${number} chips are revealed ${chips_image}'), [
            'slot' => $slot,
            'chips' => $chips,
            'chips_image' => [],
            'number' => count($chips), // for logs
            'preserve' => ['chips'],
        ]);
    }

    function stRevealChips() {
        $phase = $this->getPhase() + 1;
        $this->setGlobalVariable(PHASE, $phase);

        if ($phase == 4) {
            foreach ([4, 5] as $slot) {
                $chip = $this->getChipFromDb($this->chips->pickCardForLocation('bag', 'table', $slot));
                $this->notifRevealChips($slot, [$chip]);
            }
            $chips = $this->getChipsFromDb(
                $this->chips->pickCardForLocation('bag', 'table', 5),
                $this->chips->pickCardForLocation('bag', 'table', 6),
            );
        } else {
            $number = 6 - $phase;
            $chips = $this->getChipsFromDb($this->chips->pickCardsForLocation($number, 'bag', 'table', $phase));
            $this->notifRevealChips($phase, $chips);
        }

        $nextState = '';
        switch ($phase) {
            case 1: case 2:
                $nextState = 'discard';
                break;
            case 3:
                $nextState = 'place';
                break;
            case 4:
                $nextState = 'endRound';
                break;
        }
        
        $this->gamestate->nextState($nextState);
    }

    function stEndTurn() {
        // TODO score

        $this->cards->moveAllCardsInLocation('hand', 'deck');
        $this->cards->shuffle('deck');
        $this->chips->moveAllCardsInLocation('table', 'bag');
        $this->chips->shuffle('bag');
        self::notifyAllPlayers('endTurn', '', []);

        $end = $this->getMaxPlayerTokens() >= 4;

        $this->gamestate->nextState($end ? 'endScore' : 'newTurn');
    }

    function stEndScore() {
        $this->DbQuery("UPDATE player SET player_score = player_rewards");

        $this->gamestate->nextState('endGame');
    }
}
