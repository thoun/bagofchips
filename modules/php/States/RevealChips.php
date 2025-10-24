<?php
declare(strict_types=1);

namespace Bga\Games\BagOfChips\States;

use Bga\GameFramework\StateType;
use Bga\GameFramework\States\GameState;
use Bga\Games\BagOfChips\Game;

class RevealChips extends GameState
{
    public function __construct(
        protected Game $game,
    ) {
        parent::__construct(
            $game,
            id: \ST_REVEAL_CHIPS,
            type: StateType::GAME,
            updateGameProgression: true,
        );
    }

    public function onEnteringState()
    {
        $phase = $this->game->getPhase() + 1;
        $this->game->setGlobalVariable(\PHASE, $phase);

        foreach ($this->game->getPlayersIds() as $playerId) {
            $this->game->giveExtraTime($playerId);

            if ($phase === 4) {
                $this->notify->all('placeCards', clienttranslate('${player_name} places remaining Objective cards'), [
                    'playerId' => $playerId,
                    'player_name' => $this->game->getPlayerNameById($playerId),
                    'minus' => $this->game->getCardsByLocation('minus', $playerId),
                    'plus' => $this->game->getCardsByLocation('plus', $playerId),
                ]);
            }
        }

        if ($phase === 4) {
            $this->notifyRevealChips(4, [$this->game->getChipFromDb($this->game->chips->pickCardForLocation('bag', 'table', 4))]);

            $this->notify->all('wait3000', clienttranslate('Suspens for the last one...'), []);

            $this->notifyRevealChips(5, [$this->game->getChipFromDb($this->game->chips->pickCardForLocation('bag', 'table', 5))]);

            $this->notify->all('wait1000', '', []);
        } else {
            $number = 6 - $phase;
            $chips = $this->game->getChipsFromDb($this->game->chips->pickCardsForLocation($number, 'bag', 'table', $phase));
            $this->notifyRevealChips($phase, $chips);
        }

        if ($phase === 4) {
            return \ST_MULTIPLAYER_BEFORE_END_ROUND;
        } else if ($phase === 3) {
            return \ST_MULTIPLAYER_PLACE_CARDS;
        }
        return \ST_MULTIPLAYER_DISCARD_CARDS;
    }

    private function notifyRevealChips(int $slot, array $chips): void
    {
        $count = count($chips);

        $message = $count > 1
            ? clienttranslate('${number} new chips are revealed ${chips_images}')
            : ($slot === 4
                ? clienttranslate('One new chip is revealed... ${chips_images}')
                : clienttranslate('And finally the last chip is revealed! ${chips_images}'));

        $this->notify->all('revealChips', $message, [
            'slot' => $slot,
            'chips' => $chips,
            'chips_images' => '',
            'number' => $count,
            'preserve' => ['chips'],
        ]);
    }
}
