<?php
declare(strict_types=1);

namespace Bga\Games\BagOfChips\States;

use Bga\GameFramework\StateType;
use Bga\GameFramework\States\GameState;
use Bga\Games\BagOfChips\Game;

class EndRound extends GameState
{
    public function __construct(
        protected Game $game,
    ) {
        parent::__construct(
            $game,
            id: \ST_END_ROUND,
            type: StateType::GAME,
        );

        $this->updateGameProgression = true;
    }

    public function onEnteringState()
    {
        $scoreRound = $this->game->getGlobalVariable(\ROUND_RESULT, true);

        if ($scoreRound['end']) {
            $this->game->DbQuery("UPDATE player SET player_score = player_rewards, player_score_aux = player_round_score");
            return EndScore::class;
        }

        $this->game->deleteGlobalVariable(\ROUND_RESULT);

        $this->game->cards->moveAllCardsInLocation(null, 'deck');
        $this->game->cards->shuffle('deck');
        $this->game->chips->moveAllCardsInLocation(null, 'bag');
        $this->game->chips->shuffle('bag');

        $this->notify->all('endRound', '', []);

        return StartRound::class;
    }
}
