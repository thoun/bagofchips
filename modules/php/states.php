<?php

trait StateTrait {

//////////////////////////////////////////////////////////////////////////////
//////////// Game state actions
////////////

    /*
        Here, you can create methods defined as "game state actions" (see "action" property in states.inc.php).
        The action method of state X is called everytime the current game state is set to X.
    */

    function displayRoundResults(array $roundScores, array $rewards) {
        /// Display table window with results ////
    
        $table = [];
        $handResultLogHtml = "<table class='round-result'>";

        $players = $this->loadPlayersBasicInfos();

        foreach($roundScores as $roundScore) {
            $playerId = intval($roundScore['id']);
            $playerName = $players[$playerId]['player_name'];
            $playerResult = [
                -intval($roundScore['score_minus']), 
                intval($roundScore['score_plus']), 
                intval($roundScore['score']), 
                $rewards[$playerId] ?? 0, 
                $this->getPlayerRewards($playerId),
            ];

            $table[$playerId] = $playerResult;
            $handResultLogHtml .= "<tr><th><strong style='color: #".$players[$playerId]['player_color'].";'>$playerName</strong></th><td>".$roundScore['score']."</td></tr>";
        }
        $handResultLogHtml .= "</table>";
        
        $this->notifyAllPlayers('showRoundResult', $handResultLogHtml, [
            "table" => $table,
        ]);

        return $table;
    }

    function stBeforeEndRound() {
        $scoreRound = $this->scoreRound();
        $instantWinner = $scoreRound['instantWinner'];

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
            for ($i = 0; $i < count($roundScores) && $roundScores[$i]['score'] == $roundScores[0]['score']; $i++) {
                $firsts[] = $roundScores[$i]['id'];
            }

            if (count($firsts) <= 1) {
                for ($i = 1; $i < count($roundScores) && $roundScores[$i]['score'] == $roundScores[1]['score']; $i++) {
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

        $table = $this->displayRoundResults($roundScores, $rewards);

        $scoreRound['table'] = $table;
        $scoreRound['end'] = $end;
        $this->setGlobalVariable(ROUND_RESULT, $scoreRound);

        if ($end) {
            $this->gamestate->nextState('endRound');
        } else {
            $this->gamestate->setAllPlayersMultiactive();
        }
    }
}
