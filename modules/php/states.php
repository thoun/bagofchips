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
        $this->DbQuery("UPDATE player SET player_round_score = 0");
        
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
        $instantWinner = $this->scoreRound();

        if ($instantWinner != null) {
            $this->DbQuery("UPDATE player SET player_score = 1 WHERE player_id = $instantWinner");
            $this->gamestate->jumpToState(ST_END_GAME);
            return;
        }

        $sql = "SELECT player_id id, player_round_score score FROM player ORDER BY score DESC";
        $roundScores = array_values(self::getCollectionFromDb($sql));
        foreach ($roundScores as &$roundScore) {
            foreach ($roundScore as $key => $value) {
                $roundScore[$key] = intval($value);
            }
        }

        $firsts = [];
        $seconds = [];

        if (count($roundScores) == 2) { // special rules for 2 players game
            $firsts[] = $roundScores[0]['id'];
            if ($roundScores[1]['score'] == $roundScores[0]['score']) {
                $firsts[] = $roundScores[1]['id'];
            }
        } else {
            for ($i = 0; $roundScores[$i]['score'] == $roundScores[0]['score']; $i++) {
                $firsts[] = $roundScores[$i]['id'];
            }

            if (count($firsts) <= 1) {
                for ($i = 1; $roundScores[$i]['score'] == $roundScores[1]['score']; $i++) {
                    if ($roundScores[$i]['score'] > -9999) {
                        $seconds[] = $roundScores[$i]['id'];
                    }
                }
            }
        }

        foreach ($firsts as $first) {
            $this->incPlayerRewards($first, count($roundScores) > 2 ? 2 : 1, clienttranslate('${player_name} receives ${incScore} reward(s)'));
        }

        if (count($firsts) > 1 && count($roundScores) > 2) {
            self::notifyAllPlayers('log', clienttranslate('Multiple players are tied for the first place, no one else receives a reward.'), []);
        }

        foreach ($seconds as $second) {
            $this->incPlayerRewards($second, 1, clienttranslate('${player_name} receives ${incScore} reward(s)'));
        }

        $end = $this->getMaxPlayerTokens() >= $this->getTokensToWin();
        if ($end) {

            $sql = "SELECT player_id id, player_rewards rewards, player_round_score score FROM player ORDER BY rewards DESC, score DESC";
            $finalScores = array_values(self::getCollectionFromDb($sql));
            foreach ($finalScores as &$finalScore) {
                foreach ($finalScore as $key => $value) {
                    $finalScore[$key] = intval($value);
                }
            }
            if ($finalScore[0]['rewards'] == $finalScore[1]['rewards'] && $finalScore[0]['score'] == $finalScore[1]['score']) {
                self::notifyAllPlayers('log', clienttranslate('Multiple players are tied in the number of rewards. As they are also tied in the last round score, another round is played.'), []);
                $end = false;
            }
        }
        
        if (!$end) {
            $this->cards->moveAllCardsInLocation('hand', 'deck');
            $this->cards->shuffle('deck');
            $this->chips->moveAllCardsInLocation('table', 'bag');
            $this->chips->shuffle('bag');
            self::notifyAllPlayers('endTurn', '', []);
        }

        $this->gamestate->nextState($end ? 'endScore' : 'newTurn');
    }

    function stEndScore() {
        $this->DbQuery("UPDATE player SET player_score = player_rewards, player_score_aux = player_round_score");

        $this->gamestate->nextState('endGame');
    }
}
