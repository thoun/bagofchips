<?php

use Bga\GameFramework\Actions\Types\IntArrayParam;

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

    public function actDiscardCards(#[IntArrayParam] array $ids) {
        $args = $this->argDiscardCards();

        if ($args['number'] != count($ids)) {
            throw new BgaUserException("Invalid card count");
        }

        $playerId = intval($this->getCurrentPlayerId());
        $hand = $this->getCardsByLocation('hand', $playerId);

        if (array_any($ids, fn($id) => !array_any($hand, fn($card) => $card->id == $id))) {
            throw new BgaUserException("You must select your own cards");
        }

        $this->cards->moveCards($ids, 'discard', $playerId);

        self::notifyAllPlayers('discardCards', clienttranslate('${player_name} discards ${number} Objective cards'), [
            'playerId' => $playerId,
            'player_name' => $this->getPlayerNameById($playerId),
            'discard' => Card::onlyIds($this->getCardsByLocation('discard', $playerId)),
            'number' => $args['number'], // for logs
        ]);

        $this->gamestate->setPlayerNonMultiactive($playerId, 'next');
    }
}
