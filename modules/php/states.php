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

}
