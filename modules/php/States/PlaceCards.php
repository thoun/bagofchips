<?php
declare(strict_types=1);

namespace Bga\Games\BagOfChips\States;

use Bga\GameFramework\Actions\Types\IntArrayParam;
use Bga\GameFramework\StateType;
use Bga\GameFramework\States\GameState;
use Bga\GameFramework\States\PossibleAction;
use Bga\GameFramework\UserException;
use Bga\Games\BagOfChips\Game;

class PlaceCards extends GameState
{
    public function __construct(
        protected Game $game,
    ) {
        parent::__construct(
            $game,
            id: \ST_MULTIPLAYER_PLACE_CARDS,
            type: StateType::MULTIPLE_ACTIVE_PLAYER,
            name: 'placeCards',
            description: clienttranslate('Waiting for other players'),
            descriptionMyTurn: clienttranslate('${you} must place 1 Objective card in [-] (remaining objectives will be placed on [+] side)'),
        );
    }

    public function onEnteringState(): void
    {
        $this->gamestate->setAllPlayersMultiactive();
    }

    #[PossibleAction]
    public function actPlaceCards(#[IntArrayParam] array $minus, #[IntArrayParam] array $plus, int $currentPlayerId) {
        if (count($minus) != 1 || count($plus) != 2) {
            throw new UserException("Invalid card count");
        }

        $hand = $this->game->getCardsByLocation('hand', $currentPlayerId);

        if (array_any($minus, fn($id) => !array_any($hand, fn($card) => $card->id == $id))) {
            throw new UserException("You must select your own cards");
        }
        if (array_any($plus, fn($id) => !array_any($hand, fn($card) => $card->id == $id))) {
            throw new UserException("You must select your own cards");
        }

        $this->game->cards->moveCards($minus, 'minus', $currentPlayerId);
        $this->game->cards->moveCards($plus, 'plus', $currentPlayerId);

        $this->notify->player($currentPlayerId, 'placeCards', '', [
            'playerId' => $currentPlayerId,
            'player_name' => $this->game->getPlayerNameById($currentPlayerId),
            'minus' => $this->game->getCardsByLocation('minus', $currentPlayerId),
            'plus' => $this->game->getCardsByLocation('plus', $currentPlayerId),
        ]);

        $this->gamestate->setPlayerNonMultiactive($currentPlayerId, RevealChips::class);
    }

    public function zombie(int $playerId) {
        $hand = $this->game->getCardsByLocation('hand', $playerId);
        $ids = array_map(fn($card) => $card->id, $hand);
        $minus = [$this->getRandomZombieChoice($ids)];
        $plus = array_values(array_diff($ids, $minus));
        return $this->actPlaceCards($minus, $plus, $playerId);
    }
}
