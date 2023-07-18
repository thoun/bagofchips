<?php

trait UtilTrait {

    //////////////////////////////////////////////////////////////////////////////
    //////////// Utility functions
    ////////////

    function array_find(array $array, callable $fn) {
        foreach ($array as $value) {
            if($fn($value)) {
                return $value;
            }
        }
        return null;
    }

    function array_findIndex(array $array, callable $fn) {
        $index = 0;
        foreach ($array as $value) {
            if($fn($value)) {
                return $index;
            }
            $index++;
        }
        return null;
    }

    function array_find_key(array $array, callable $fn) {
        foreach ($array as $key => $value) {
            if($fn($value)) {
                return $key;
            }
        }
        return null;
    }

    function array_some(array $array, callable $fn) {
        foreach ($array as $value) {
            if($fn($value)) {
                return true;
            }
        }
        return false;
    }
    
    function array_every(array $array, callable $fn) {
        foreach ($array as $value) {
            if(!$fn($value)) {
                return false;
            }
        }
        return true;
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

    function getPlayerName(int $playerId) {
        return self::getUniqueValueFromDB("SELECT player_name FROM player WHERE player_id = $playerId");
    }

    function incPlayerRewards(int $playerId, int $amount, $message = '', $args = []) {
        if ($amount != 0) {
            $this->DbQuery("UPDATE player SET `player_rewards` = `player_rewards` + $amount WHERE player_id = $playerId");
        }
            
        $this->notifyAllPlayers('rewards', $message, [
            'playerId' => $playerId,
            'player_name' => $this->getPlayerName($playerId),
            'newScore' => intval(self::getUniqueValueFromDB("SELECT player_rewards FROM player WHERE player_id = $playerId")),
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
                return $this->array_every($counts, fn($count) => $count >= $card->params[0]);
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
        $lastChip = $this->array_find($chips, fn($chip) => $chip->locationArg == 5);
        
        $playersIds = $this->getPlayersIds();
        $instantWinner = null;
        $gameWon = false;

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
                    $this->DbQuery("UPDATE player SET player_round_score = player_round_score - $points WHERE player_id = $playerId");
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
                
                self::notifyAllPlayers('scoreCard', $message, [
                    'playerId' => $playerId,
                    'player_name' => $this->getPlayerName($playerId),
                    'card' => $card,
                    'card_image' => '',
                    'preserve' => ['card'],
                    'score' => $scored ? -$points : 0,
                    'points' => $scored ? -$points : 0, // for log
                ]);
            }
            
            if (!$roundLost) {
                foreach ($plus as $card) {
                    $scored = $this->isCardScored($card, $counts, $lastChip);
                    $points = $card->type == 6 ? $card->points * $counts[$card->params[0]] : $card->points;
                    if ($scored) {
                        $this->DbQuery("UPDATE player SET player_round_score = player_round_score + $points WHERE player_id = $playerId");

                        if ($points == 99999) {
                            $instantWinner = $playerId;
                            $gameWon = true;
                        }
                        
                        $message = $points == 99999 ?
                            clienttranslate('${player_name} scores the [-] card and wins the game! ${card_image}') :
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
                    
                    self::notifyAllPlayers('scoreCard', $message, [
                        'playerId' => $playerId,
                        'player_name' => $this->getPlayerName($playerId),
                        'card' => $card,
                        'card_image' => '',
                        'preserve' => ['card'],
                        'score' => $scored ? $points : 0,
                        'points' => $scored ? $points : 0, // for log
                    ]);
                }
            }
            
            if ($gameWon) {
                break;
            }

            if (!$roundLost && !$gameWon) {
                self::notifyAllPlayers('log', clienttranslate('${player_name} scores ${number} points this round'), [
                    'playerId' => $playerId,
                    'player_name' => $this->getPlayerName($playerId),
                    'number' => $roundScore, // for log
                ]);
            }
        }

        return $instantWinner;
    }
}
