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
        $this->incStat(1, 'roundNumber');
        $this->setGlobalVariable(PHASE, 0);
        $this->DbQuery("UPDATE player SET player_round_score = 0, player_round_score_minus = 0, player_round_score_plus = 0");

        self::notifyAllPlayers('wait1000', clienttranslate('Shuffling the chips for the new round...'), []);
        
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
        $count = count($chips);

        $message = $count > 1 ? clienttranslate('${number} new chips are revealed ${chips_images}') :
         ($slot == 4 ? clienttranslate('One new chip is revealed... ${chips_images}') : clienttranslate('And finally the last chip is revealed! ${chips_images}'));

        self::notifyAllPlayers('revealChips', $message, [
            'slot' => $slot,
            'chips' => $chips,
            'chips_images' => '',
            'number' => $count, // for logs
            'preserve' => ['chips'],
        ]);
    }

    function stRevealChips() {
        $phase = $this->getPhase() + 1;
        $this->setGlobalVariable(PHASE, $phase);

        if ($phase == 4) {
            $this->notifRevealChips(4, [$this->getChipFromDb($this->chips->pickCardForLocation('bag', 'table', 4))]);

            self::notifyAllPlayers('wait1000', clienttranslate('Suspens for the last one...'), []);

            $this->notifRevealChips(5, [$this->getChipFromDb($this->chips->pickCardForLocation('bag', 'table', 5))]);

            self::notifyAllPlayers('wait1000', '', []);
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

    function displayRoundResults(array $roundScores, array $rewards, bool $endGame) {
        /// Display table window with results ////
    
        // Header line
        $headers = [''];
        $handMinusPoints = [ ['str' => clienttranslate('Hand [-] points'), 'args' => [] ] ];
        $handPlusPoints = [ ['str' => clienttranslate('Hand [+] points'), 'args' => [] ] ];
        $handPoints = [ ['str' => clienttranslate('Hand total points'), 'args' => [] ] ];
        $handRewardPoints = [ ['str' => clienttranslate('Hand rewards'), 'args' => [] ] ];
        $totalRewardPoints = [ ['str' => clienttranslate('Total rewards'), 'args' => [] ] ];
        $handResultLogHtml = "<table class='round-result'>";

        $players = $this->loadPlayersBasicInfos();

        foreach($roundScores as $roundScore) {
            $playerId = intval($roundScore['id']);
            $playerName = $players[$playerId]['player_name'];

            $headers[] = [
                    'str' => '${player_name}',
                    'args' => ['player_name' => $playerName],
                    'type' => 'header'
            ];
            $handMinusPoints[] = intval($roundScore['score_minus']) > 999 ? '-' : -intval($roundScore['score_minus']);
            $handPlusPoints[] = intval($roundScore['score_plus']) > 999 ? '-' : intval($roundScore['score_plus']);
            $playerHandPoints = intval($roundScore['score']) > 999 ? '-' : intval($roundScore['score']);
            $handPoints[] = $playerHandPoints;
            $handRewardHtml = '';
            for ($i = 0; $i < ($rewards[$playerId] ?? 0); $i++) {
                $handRewardHtml .= '<div class="reward icon"></div>';
            }
            $handRewardPoints[] = $handRewardHtml == '' ? '-' : $handRewardHtml;

            $playerRewards = $this->getPlayerRewards($playerId);
            $totalRewardHtml = '';
            for ($i = 0; $i < $playerRewards; $i++) {
                $totalRewardHtml .= '<div class="reward icon"></div>';
            }
            $totalRewardPoints[] = $totalRewardHtml == '' ? '-' : $totalRewardHtml;

            $handResultLogHtml .= "<tr><td><strong style='color: #".$players[$playerId]['player_color'].";'>$playerName</strong></td><td>$playerHandPoints</td></td>";
        }
        $handResultLogHtml .= "</table>";
        
        $table = [$headers, $handMinusPoints, $handPlusPoints, $handPoints, $handRewardPoints, $totalRewardPoints];
        $this->notifyAllPlayers('tableWindow', $handResultLogHtml, [
            "id" => 'finalScoring',
            "title" =>  clienttranslate('Result of hand'),
            "table" => $table,
            "closing" => $endGame ? clienttranslate("End of game") : clienttranslate("Next hand"),
        ]);
    }

    function stEndRound() {
        $instantWinner = $this->scoreRound();

        if ($instantWinner != null) {
            $this->DbQuery("UPDATE player SET player_score = 1 WHERE player_id = $instantWinner");
            $this->gamestate->nextState('endScore');
            return;
        }

        $sql = "SELECT player_id id, player_round_score score, player_round_score_minus score_minus, player_round_score_plus score_plus FROM player ORDER BY score DESC";
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

        $rewards = [];
        foreach ($firsts as $first) {
            $reward = count($roundScores) > 2 ? 2 : 1;
            $this->incPlayerRewards($first, $reward, clienttranslate('${player_name} receives ${incScore} reward(s)'));
            $rewards[$first] = $reward;
        }

        if (count($firsts) > 1 && count($roundScores) > 2) {
            self::notifyAllPlayers('log', clienttranslate('Multiple players are tied for the first place, no one else receives a reward.'), []);
        }

        foreach ($seconds as $second) {
            $this->incPlayerRewards($second, 1, clienttranslate('${player_name} receives ${incScore} reward(s)'));
            $rewards[$second] = 1;
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
            if ($finalScores[0]['rewards'] == $finalScores[1]['rewards'] && $finalScores[0]['score'] == $finalScores[1]['score']) {
                self::notifyAllPlayers('log', clienttranslate('Multiple players are tied in the number of rewards. As they are also tied in the last round score, another round is played.'), []);
                $end = false;
            }
        }

        $this->displayRoundResults($roundScores, $rewards, $end);
        
        if ($end) {
            $this->DbQuery("UPDATE player SET player_score = player_rewards, player_score_aux = player_round_score");
        } else  {
            $this->cards->moveAllCardsInLocation(null, 'deck');
            $this->cards->shuffle('deck');
            $this->chips->moveAllCardsInLocation(null, 'bag');
            $this->chips->shuffle('bag');
            self::notifyAllPlayers('endRound', '', []);
        }

        $this->gamestate->nextState($end ? 'endScore' : 'newRound');
    }

    function stEndScore() {
        $playersIds = $this->getPlayersIds();
        $rounds = $this->getStat('roundNumber');

        $this->setStat($this->getStat('validatedMinusObjective') / $rounds, 'avgValidatedMinusObjective');
        $this->setStat($this->getStat('validatedPlusObjective') / $rounds, 'avgValidatedPlusObjective');
        $this->setStat($this->getStat('pointsMinusObjectives') / $rounds, 'avgPointsMinusObjectives');
        $this->setStat($this->getStat('pointsPlusObjectives') / $rounds, 'avgPointsPlusObjectives');
        $this->setStat($this->getStat('rewards') / $rounds, 'avgRewardsPerRound');

        foreach($playersIds as $playerId) {
            $this->setStat($this->getStat('validatedMinusObjective', $playerId) / $rounds, 'avgValidatedMinusObjective', $playerId);
            $this->setStat($this->getStat('validatedPlusObjective', $playerId) / $rounds, 'avgValidatedPlusObjective', $playerId);
            $this->setStat($this->getStat('pointsMinusObjectives', $playerId) / $rounds, 'avgPointsMinusObjectives', $playerId);
            $this->setStat($this->getStat('pointsPlusObjectives', $playerId) / $rounds, 'avgPointsPlusObjectives', $playerId);
            $this->setStat($this->getStat('rewards', $playerId) / $rounds, 'avgRewardsPerRound', $playerId);
        }

        $this->gamestate->nextState('endGame');
    }
}
