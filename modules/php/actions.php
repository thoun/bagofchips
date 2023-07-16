<?php

if (!function_exists('str_starts_with')) {
    function str_starts_with($haystack, $needle) {
        return (string)$needle !== '' && strncmp($haystack, $needle, strlen($needle)) === 0;
    }
}

trait ActionTrait {

    //////////////////////////////////////////////////////////////////////////////
    //////////// Player actions
    //////////// 
    
    /*
        Each time a player is doing some game action, one of the methods below is called.
        (note: each method below must match an input method in nicodemus.action.php)
    */

    public function goTrade() {
        self::checkAction('goTrade');

        $this->gamestate->nextState('trade');
    }

    public function playCard(int $id) {
        self::checkAction('playCard');

        if (boolval($this->getGameStateValue(RECRUIT_DONE))) {
            throw new BgaUserException("Invalid action");
        }

        $playerId = intval($this->getActivePlayerId());
            

        $hand = $this->getCardsByLocation('hand', $playerId);
        $card = $this->array_find($hand, fn($c) => $c->id == $id);

        if ($card == null || $card->location != 'hand' || $card->locationArg != $playerId) {
            throw new BgaUserException("You can't play this card");
        }

        $this->cards->moveCard($card->id, 'played'.$playerId.'-'.$card->color, intval($this->chips->countCardInLocation('played'.$playerId.'-'.$card->color)));

        $cardsOfColor = $this->getCardsByLocation('played'.$playerId.'-'.$card->color);
        $gains = array_map(fn($card) => $card->gain, $cardsOfColor);
        $groupGains = $this->groupGains($gains);
        $effectiveGains = $this->gainResources($playerId, $groupGains, 'recruit');

        self::notifyAllPlayers('playCard', clienttranslate('${player_name} plays a ${card_color} ${card_type} card from their hand and gains ${gains}'), [
            'playerId' => $playerId,
            'player_name' => $this->getPlayerName($playerId),
            'card' => $card,
            'effectiveGains' => $effectiveGains,
            'gains' => $effectiveGains, // for logs
            'card_type' => $this->getGainName($card->gain), // for logs
            'card_color' => $this->getColorName($card->color), // for logs
        ]);

        $this->setGameStateValue(PLAYED_CARD_COLOR, $card->color);

        $argChooseNewCard = $this->argChooseNewCard();
        if ($argChooseNewCard['allFree']) {
            self::notifyAllPlayers('log', clienttranslate('${player_name} can recruit any viking for free thanks to ${artifact_name} effect'), [
                'player_name' => $this->getPlayerName($playerId),
                'artifact_name' => $this->getArtifactName(ARTIFACT_CAULDRON), // for logs
                'i18n' => ['artifact_name'],
            ]);
        }

        $this->incStat(1, 'playedCards');
        $this->incStat(1, 'playedCards', $playerId);

        $allGains = array_reduce($effectiveGains, fn($a, $b) => $a + $b, 0);
        $this->incStat($allGains, 'assetsCollectedByPlayedCards');
        $this->incStat($allGains, 'assetsCollectedByPlayedCards', $playerId);
        foreach ($effectiveGains as $type => $count) {
            if ($count > 0) {
                $this->incStat($count, 'assetsCollectedByPlayedCards'.$type);
                $this->incStat($count, 'assetsCollectedByPlayedCards'.$type, $playerId);
            }
        }

        $this->gamestate->nextState('chooseNewCard');
    }

    public function chooseNewCard(int $id) {
        self::checkAction('chooseNewCard');

        $playerId = intval($this->getActivePlayerId());

        $args = $this->argChooseNewCard();
        $card = $this->array_find($args['centerCards'], fn($card) => $card->id == $id);

        if ($card == null || $card->location != 'slot') {
            throw new BgaUserException("You can't play this card");
        }
        $slotColor = $card->locationArg;

        if ($slotColor != $args['freeColor'] && !$args['allFree']) {
            if ($args['recruits'] < 1) {
                throw new BgaUserException("Not enough recruits");
            } else {
                $this->incPlayerRecruit($playerId, -1, clienttranslate('${player_name} pays a recruit to choose the new card'), []);
        
                $this->incStat(1, 'recruitsUsedToChooseCard');
                $this->incStat(1, 'recruitsUsedToChooseCard', $playerId);
            }
        }
        
        $this->cards->moveCard($card->id, 'hand', $playerId);

        self::notifyAllPlayers('takeCard', clienttranslate('${player_name} takes the ${card_color} ${card_type} card from the table (${color} column)'), [
            'playerId' => $playerId,
            'player_name' => $this->getPlayerName($playerId),
            'card' => $card,
            'color' => $this->getColorName($slotColor), // for logs
            'card_type' => $this->getGainName($card->gain), // for logs
            'card_color' => $this->getColorName($card->color), // for logs
        ]);

        if ($this->getAvailableDeckCards() >= 1) {
            $this->endOfRecruit($playerId, $slotColor);
        } else {
            $this->setGlobalVariable(REMAINING_CARDS_TO_TAKE, [
                'playerId' => $playerId,
                'slotColor' => $slotColor,
                'phase' => 'recruit',
                'remaining' => 1,
            ]);
            $this->gamestate->nextState('discardCardsForDeck');
        }
    }

    public function endOfRecruit(int $playerId, int $slotColor) {
        $newTableCard = $this->getCardFromDb($this->cards->pickCardForLocation('deck', 'slot', $slotColor));
        $newTableCard->location = 'slot';
        $newTableCard->locationArg = $slotColor;

        self::notifyAllPlayers('newTableCard', '', [
            'card' => $newTableCard,
            'cardDeckTop' => Card::onlyId($this->getCardFromDb($this->cards->getCardOnTop('deck'))),
            'cardDeckCount' => intval($this->cards->countCardInLocation('deck')) + 1, // to count the new card
        ]);

        $this->setGameStateValue(RECRUIT_DONE, 1);
        $this->setGameStateValue(EXPLORE_DONE, 1);

        $this->redirectAfterAction($playerId, true);
    }

    public function takeChip(int $id) {
        self::checkAction('takeChip');

        if (boolval($this->getGameStateValue(EXPLORE_DONE))) {
            throw new BgaUserException("Invalid action");
        }

        $args = $this->argPlayAction();
        $chip = $this->array_find($args['possibleChips'], fn($c) => $c->id == $id);

        if ($chip == null) {
            throw new BgaUserException("You can't take this chip");
        }

        $this->setGameStateValue(SELECTED_CHIP, $id);

        $this->gamestate->nextState('payChip');
    }

    public function payChip(array $ids, int $recruits) {
        self::checkAction('payChip');

        $playerId = intval($this->getActivePlayerId());
        
        if ($recruits > 0 && $this->getPlayer($playerId)->recruit < $recruits) {
            throw new BgaUserException("Not enough recruits");
        }

        $chip = $this->getChipFromDb($this->chips->getCard($this->getGameStateValue(SELECTED_CHIP)));
        $fromReserve = $chip->location == 'reserved';
        
        // will contain only selected cards of player
        $playedCardsByColor = [];
        $selectedPlayedCardsColors = [];
        $cardsToDiscard = [];
        if (count($ids) > 0) {
            $playedCardsByColor = $this->getPlayedCardsByColor($playerId);
            foreach ([1,2,3,4,5] as $color) {
                $playedCardsByColor[$color] = array_values(array_filter($playedCardsByColor[$color], fn($card) => in_array($card->id, $ids)));
                $selectedPlayedCardsColors[$color] = count($playedCardsByColor[$color]);
                $cardsToDiscard = array_merge($cardsToDiscard, $playedCardsByColor[$color]);
            }
        }

        $valid = $this->canTakeChip($chip, $selectedPlayedCardsColors, $recruits, true);
        if (!$valid) {
            throw new BgaUserException("Invalid payment for this chip");
        }

        if ($recruits > 0) {
            $this->incPlayerRecruit($playerId, -$recruits, clienttranslate('${player_name} pays ${number} recruit(s) for the selected chip'), [
                'number' => $recruits, // for logs
            ]);
            $this->incStat($recruits, 'recruitsUsedToPayChip');
            $this->incStat($recruits, 'recruitsUsedToPayChip', $playerId);
        }

        if (count($cardsToDiscard)) {
            $this->cards->moveCards(array_map(fn($card) => $card->id, $cardsToDiscard), 'discard');

            self::notifyAllPlayers('discardCards', clienttranslate('${player_name} discards ${number} cards(s) for the selected chip'), [
                'playerId' => $playerId,
                'player_name' => $this->getPlayerName($playerId),
                'cards' => $cardsToDiscard,
                'number' => $recruits, // for logs
                'cardDiscardCount' => intval($this->cards->countCardInLocation('discard')),
            ]);
        }

        $chipIndex = intval($this->chips->countCardInLocation('played'.$playerId));
        $this->chips->moveCard($chip->id, 'played'.$playerId, $chipIndex);

        $effectiveGains = $this->gainResources($playerId, $chip->immediateGains, 'explore');
        $type = $chip->type == 2 ? 'B' : 'A';

        self::notifyAllPlayers('takeChip', clienttranslate('${player_name} takes a chip from line ${letter} and gains ${gains}'), [
            'playerId' => $playerId,
            'player_name' => $this->getPlayerName($playerId),
            'chip' => $chip,
            'effectiveGains' => $effectiveGains,
            'gains' => $effectiveGains, // for logs
            'letter' => $type, // for logs
        ]);
                    
        $this->incStat(1, 'discoveredChips');
        $this->incStat(1, 'discoveredChips', $playerId);
        $this->incStat(1, 'discoveredChips'.$chip->type);
        $this->incStat(1, 'discoveredChips'.$chip->type, $playerId);

        $allGains = array_reduce($effectiveGains, fn($a, $b) => $a + $b, 0);
        $this->incStat($allGains, 'assetsCollectedByChip');
        $this->incStat($allGains, 'assetsCollectedByChip', $playerId);
        foreach ($effectiveGains as $type => $count) {
            if ($count > 0) {
                $this->incStat($count, 'assetsCollectedByChip'.$type);
                $this->incStat($count, 'assetsCollectedByChip'.$type, $playerId);
            }
        }

        $remainingCardsToTake = $this->getGlobalVariable(REMAINING_CARDS_TO_TAKE);
        if ($remainingCardsToTake != null) {
            $remainingCardsToTake->fromReserve = $fromReserve;
            $remainingCardsToTake->chip = $chip;
            $remainingCardsToTake->chipIndex = $chipIndex;
            $this->setGlobalVariable(REMAINING_CARDS_TO_TAKE, $remainingCardsToTake);

            $this->gamestate->nextState('discardCardsForDeck');
        } else {
            $this->endExplore($playerId, $fromReserve, $chip, $chipIndex);
        }
    }

    public function endExplore(int $playerId, bool $fromReserve, object $chip, int $chipIndex) {
        if (!$fromReserve) {
            $type = $chip->type == 2 ? 'B' : 'A';
            $newChip = $this->getChipFromDb($this->chips->pickCardForLocation('deck'.$type, 'slot'.$type, $chip->locationArg));
            $newChip->location = 'slot'.$type;
            $newChip->locationArg = $chip->locationArg;

            self::notifyAllPlayers('newTableChip', '', [
                'chip' => $newChip,
                'letter' => $type,
                'chipDeckTop' => Chip::onlyId($this->getChipFromDb($this->chips->getCardOnTop('deck'.$type))),
                'chipDeckCount' => intval($this->chips->countCardInLocation('deck'.$type)),
            ]);
        }

        $this->setGameStateValue(RECRUIT_DONE, 1);
        $this->setGameStateValue(EXPLORE_DONE, 1);

        $this->redirectAfterAction($playerId, true);
    }

    public function reserveChip(int $id) {
        self::checkAction('reserveChip');

        $playerId = intval($this->getActivePlayerId());

        $chip = $this->getChipFromDb($this->chips->getCard($id));

        if ($chip == null || !in_array($chip->location, ['slotA', 'slotB'])) {
            throw new BgaUserException("You can't reserve this chip");
        }

        $this->chips->moveCard($chip->id, 'reserved', $playerId);
        $type = $chip->type == 2 ? 'B' : 'A';

        self::notifyAllPlayers('reserveChip', clienttranslate('${player_name} takes a chip from line ${letter}'), [
            'playerId' => $playerId,
            'player_name' => $this->getPlayerName($playerId),
            'chip' => $chip,
            'letter' => $type, // for logs
        ]);

        $newChip = $this->getChipFromDb($this->chips->pickCardForLocation('deck'.$type, 'slot'.$type, $chip->locationArg));
        $newChip->location = 'slot'.$type;
        $newChip->locationArg = $chip->locationArg;

        self::notifyAllPlayers('newTableChip', '', [
            'chip' => $newChip,
            'letter' => $type,
            'chipDeckTop' => Chip::onlyId($this->getChipFromDb($this->chips->getCardOnTop('deck'.$type))),
            'chipDeckCount' => intval($this->chips->countCardInLocation('deck'.$type)),
        ]);

        $this->gamestate->nextState('next');
    }

    public function discardTableCard(int $id) {
        self::checkAction('discardTableCard');

        $playerId = intval($this->getActivePlayerId());

        $card = $this->getCardFromDb($this->cards->getCard($id));

        if ($card == null || $card->location != 'slot') {
            throw new BgaUserException("You can't discard this card");
        }
        $slotColor = $card->locationArg;
        
        $this->cards->moveCard($card->id, 'discard');

        self::notifyAllPlayers('discardTableCard', clienttranslate('${player_name} discards ${card_color} ${card_type} card from the table (${color} column)'), [
            'playerId' => $playerId,
            'player_name' => $this->getPlayerName($playerId),
            'card' => $card,
            'color' => $this->getColorName($slotColor), // for logs
            'card_type' => $this->getGainName($card->gain), // for logs
            'card_color' => $this->getColorName($card->color), // for logs
        ]);

        $newTableCard = $this->getCardFromDb($this->cards->pickCardForLocation('deck', 'slot', $slotColor));
        $newTableCard->location = 'slot';
        $newTableCard->locationArg = $slotColor;

        self::notifyAllPlayers('newTableCard', '', [
            'card' => $newTableCard,
            'cardDeckTop' => Card::onlyId($this->getCardFromDb($this->cards->getCardOnTop('deck'))),
            'cardDeckCount' => intval($this->cards->countCardInLocation('deck')) + 1, // to count the new card
        ]);

        $this->redirectAfterAction($playerId, true);
    }

    public function pass() {
        self::checkAction('pass');

        $playerId = intval($this->getActivePlayerId());

        $this->redirectAfterAction($playerId, true);
    }

    public function trade(int $number) {
        self::checkAction('trade');

        $playerId = intval($this->getActivePlayerId());

        if ($this->getPlayer($playerId)->bracelet < $number) {
            throw new BgaUserException("Not enough bracelets");
        }

        $this->incPlayerBracelet($playerId, -$number, clienttranslate('${player_name} chooses to pay ${number} bracelet(s) to trade'), [
            'number' => $number, // for logs
        ]);

        $gains = $this->getTradeGains($playerId, $number);
        $groupGains = $this->groupGains($gains);
        $effectiveGains = $this->gainResources($playerId, $groupGains, 'trade');

        self::notifyAllPlayers('trade', clienttranslate('${player_name} gains ${gains} with traded bracelet(s)'), [
            'playerId' => $playerId,
            'player_name' => $this->getPlayerName($playerId),
            'effectiveGains' => $effectiveGains,
            'gains' => $effectiveGains, // for logs
        ]);

        $this->incStat(1, 'tradeActions');
        $this->incStat(1, 'tradeActions', $playerId);
        $this->incStat(1, 'tradeActions'.$number);
        $this->incStat(1, 'tradeActions'.$number, $playerId);
        $this->incStat($number, 'braceletsUsed');
        $this->incStat($number, 'braceletsUsed', $playerId);

        $allGains = array_reduce($effectiveGains, fn($a, $b) => $a + $b, 0);
        $this->incStat($allGains, 'assetsCollectedByTrade');
        $this->incStat($allGains, 'assetsCollectedByTrade', $playerId);
        foreach ($effectiveGains as $type => $count) {
            if ($count > 0) {
                $this->incStat($count, 'assetsCollectedByTrade'.$type);
                $this->incStat($count, 'assetsCollectedByTrade'.$type, $playerId);
            }
        }

        if ($this->getGlobalVariable(REMAINING_CARDS_TO_TAKE) != null) {
            $this->gamestate->nextState('discardCardsForDeck');
        } else {
            $this->endTrade($playerId);
        }
    }

    public function endTrade(int $playerId) {
        $this->setGameStateValue(TRADE_DONE, 1);
        $this->redirectAfterAction($playerId, false);
    }

    public function cancel() {
        self::checkAction('cancel');

        $this->gamestate->nextState('cancel');
    }

    public function endTurn() {
        self::checkAction('endTurn');

        $playerId = intval($this->getCurrentPlayerId());

        $endTurn = $this->checkEndTurnArtifacts($playerId);

        $this->gamestate->nextState(!$endTurn ? 'next' : 'endTurn');
    }

    public function discardCard(int $id) {
        self::checkAction('discardCard');

        $playerId = intval($this->getCurrentPlayerId());

        $card = $this->getCardFromDb($this->cards->getCard($id));

        if ($card == null || !str_starts_with($card->location, "played$playerId")) {
            throw new BgaUserException("You must choose a card in front of you");
        }

        $this->cards->moveCard($card->id, 'discard');

        self::notifyAllPlayers('discardCards', clienttranslate('${player_name} discards a cards to refill the deck'), [
            'playerId' => $playerId,
            'player_name' => $this->getPlayerName($playerId),
            'cards' => [$card],
            'cardDiscardCount' => intval($this->cards->countCardInLocation('discard')),
        ]);

        $this->incStat(1, 'discardedCards');
        $this->incStat(1, 'discardedCards', $playerId);

        $this->gamestate->setPlayerNonMultiactive($playerId, 'next');
    }
}
