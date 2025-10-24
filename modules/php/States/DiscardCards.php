<?php
declare(strict_types=1);

namespace Bga\Games\BagOfChips\States;

use Bga\GameFramework\Actions\Types\IntArrayParam;
use Bga\GameFramework\StateType;
use Bga\GameFramework\States\GameState;
use Bga\GameFramework\States\PossibleAction;
use Bga\GameFramework\UserException;
use Bga\Games\BagOfChips\Game;
use Card;

class DiscardCards extends GameState
{
    public function __construct(
        protected Game $game,
    ) {
        parent::__construct(
            $game,
            id: \ST_MULTIPLAYER_DISCARD_CARDS,
            type: StateType::MULTIPLE_ACTIVE_PLAYER,
            name: 'discardCards',
            description: clienttranslate('Waiting for other players'),
            descriptionMyTurn: clienttranslate('${you} must discard ${number} Objective card(s)'),
        );
    }
   
    function getArgs(): array {
        $phase = $this->game->getPhase();

        return [
            'number' => 3 - $phase,
        ];
    }

    #[PossibleAction]
    public function actDiscardCards(#[IntArrayParam] array $ids, int $currentPlayerId) {
        $phase = $this->game->getPhase();
        $number = 3 - $phase;

        if ($number != count($ids)) {
            throw new UserException("Invalid card count");
        }

        $hand = $this->game->getCardsByLocation('hand', $currentPlayerId);

        if (array_any($ids, fn($id) => !array_any($hand, fn($card) => $card->id == $id))) {
            throw new UserException("You must select your own cards");
        }

        $this->game->cards->moveCards($ids, 'discard', $currentPlayerId);

        $this->notify->all('discardCards', clienttranslate('${player_name} discards ${number} Objective cards'), [
            'playerId' => $currentPlayerId,
            'player_name' => $this->game->getPlayerNameById($currentPlayerId),
            'discard' => Card::onlyIds($this->game->getCardsByLocation('discard', $currentPlayerId)),
            'number' => $number, // for logs
        ]);

        $this->gamestate->setPlayerNonMultiactive($currentPlayerId, RevealChips::class);
    }

    public function zombie(int $playerId) {
        $phase = $this->game->getPhase();
        $number = 3 - $phase;
        $hand = $this->game->getCardsByLocation('hand', $playerId);
        $ids = array_map(fn($card) => $card->id, $hand);
        shuffle($ids); // random choice over possible moves
        $zombieChoice = array_slice(array_map(fn($card) => intval($card['id']), $hand), 0, $number);
        return $this->actDiscardCards($zombieChoice, $playerId);
    }
}
