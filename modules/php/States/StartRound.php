<?php
declare(strict_types=1);

namespace Bga\Games\BagOfChips\States;

use Bga\GameFramework\StateType;
use Bga\GameFramework\States\GameState;
use Bga\Games\BagOfChips\Game;

class StartRound extends GameState
{
    public function __construct(
        protected Game $game,
    ) {
        parent::__construct(
            $game,
            id: \ST_START_ROUND,
            type: StateType::GAME,
            description: clienttranslate('Dealing 6 Objective cards to each player...'),
        );
    }

    public function onEnteringState()
    {
        $this->game->incStat(1, 'roundNumber');
        $this->game->setGlobalVariable(\PHASE, 0);
        $this->game->DbQuery("UPDATE player SET player_round_score = 0, player_round_score_minus = 0, player_round_score_plus = 0");

        $this->game->notify->all('wait1000', clienttranslate('Shuffling the chips for the new round...'), []);

        $playersIds = $this->game->getPlayersIds();

        foreach ($playersIds as $playerId) {
            $cards = $this->game->getCardsFromDb($this->game->cards->pickCardsForLocation(6, 'deck', 'hand', $playerId));
            $this->game->notify->player($playerId, 'newHand', '', [
                'cards' => $cards,
            ]);
        }

        return \ST_REVEAL_CHIPS;
    }
}
