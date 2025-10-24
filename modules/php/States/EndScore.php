<?php
declare(strict_types=1);

namespace Bga\Games\BagOfChips\States;

use Bga\GameFramework\StateType;
use Bga\GameFramework\States\GameState;
use Bga\Games\BagOfChips\Game;

class EndScore extends GameState
{
    function __construct(
        protected Game $game,
    ) {
        parent::__construct($game,
            id: \ST_END_SCORE,
            type: StateType::GAME,
        );
    }

    function onEnteringState() {
        $playersIds = $this->game->getPlayersIds();
        $rounds = $this->game->getStat('roundNumber');

        $this->game->setStat($this->game->getStat('validatedMinusObjective') / $rounds, 'avgValidatedMinusObjective');
        $this->game->setStat($this->game->getStat('validatedPlusObjective') / $rounds, 'avgValidatedPlusObjective');
        $this->game->setStat($this->game->getStat('pointsMinusObjectives') / $rounds, 'avgPointsMinusObjectives');
        $this->game->setStat($this->game->getStat('pointsPlusObjectives') / $rounds, 'avgPointsPlusObjectives');
        $this->game->setStat($this->game->getStat('rewards') / $rounds, 'avgRewardsPerRound');

        foreach($playersIds as $playerId) {
            $this->game->setStat($this->game->getStat('validatedMinusObjective', $playerId) / $rounds, 'avgValidatedMinusObjective', $playerId);
            $this->game->setStat($this->game->getStat('validatedPlusObjective', $playerId) / $rounds, 'avgValidatedPlusObjective', $playerId);
            $this->game->setStat($this->game->getStat('pointsMinusObjectives', $playerId) / $rounds, 'avgPointsMinusObjectives', $playerId);
            $this->game->setStat($this->game->getStat('pointsPlusObjectives', $playerId) / $rounds, 'avgPointsPlusObjectives', $playerId);
            $this->game->setStat($this->game->getStat('rewards', $playerId) / $rounds, 'avgRewardsPerRound', $playerId);
        }

        return ST_END_GAME;
    }
}
