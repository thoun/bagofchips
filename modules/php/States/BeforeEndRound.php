<?php
declare(strict_types=1);

namespace Bga\Games\BagOfChips\States;

use Bga\GameFramework\StateType;
use Bga\GameFramework\States\GameState;
use Bga\GameFramework\States\PossibleAction;
use Bga\Games\BagOfChips\Game;

class BeforeEndRound extends GameState
{
    public function __construct(
        protected Game $game,
    ) {
        parent::__construct(
            $game,
            id: \ST_MULTIPLAYER_BEFORE_END_ROUND,
            type: StateType::MULTIPLE_ACTIVE_PLAYER,
            name: 'beforeEndRound',
            description: clienttranslate('Some players are seeing end round result'),
            descriptionMyTurn: clienttranslate('End round result'),
        );
    }

    public function onEnteringState()
    {
        $scoreRound = $this->game->scoreRound();
        $instantWinner = $scoreRound['instantWinner'];

        if ($instantWinner !== null) {
            $this->game->DbQuery("UPDATE player SET player_score = 1 WHERE player_id = $instantWinner");
            return EndScore::class;
        }

        $sql = "SELECT player_id id, player_round_score score, player_round_score_minus score_minus, player_round_score_plus score_plus FROM player ORDER BY score DESC";
        $roundScores = array_values($this->game->getCollectionFromDb($sql));
        foreach ($roundScores as &$roundScore) {
            $roundScore = array_map('intval', $roundScore);
        }

        $firsts = [];
        $seconds = [];

        if (count($roundScores) === 2) {
            $firsts[] = $roundScores[0]['id'];
            if ($roundScores[1]['score'] === $roundScores[0]['score']) {
                $firsts[] = $roundScores[1]['id'];
            }
        } else {
            for ($i = 0; $i < count($roundScores) && $roundScores[$i]['score'] === $roundScores[0]['score']; $i++) {
                $firsts[] = $roundScores[$i]['id'];
            }

            if (count($firsts) <= 1) {
                for ($i = 1; $i < count($roundScores) && $roundScores[$i]['score'] === $roundScores[1]['score']; $i++) {
                    if ($roundScores[$i]['score'] > -9999) {
                        $seconds[] = $roundScores[$i]['id'];
                    }
                }
            }
        }

        $rewards = [];
        foreach ($firsts as $first) {
            $reward = count($roundScores) > 2 ? 2 : 1;
            $this->game->incPlayerRewards($first, $reward, clienttranslate('${player_name} receives ${incScore} reward(s)'));
            $rewards[$first] = $reward;
        }

        if (count($firsts) > 1 && count($roundScores) > 2) {
            $this->notify->all('log', clienttranslate('Multiple players are tied for the first place, no one else receives a reward.'), []);
        }

        foreach ($seconds as $second) {
            $this->game->incPlayerRewards($second, 1, clienttranslate('${player_name} receives ${incScore} reward(s)'));
            $rewards[$second] = 1;
        }

        $end = $this->game->getMaxPlayerTokens() >= $this->game->getTokensToWin();
        if ($end) {
            $sql = "SELECT player_id id, player_rewards rewards, player_round_score score FROM player ORDER BY rewards DESC, score DESC";
            $finalScores = array_values($this->game->getCollectionFromDb($sql));
            foreach ($finalScores as &$finalScore) {
                $finalScore = array_map('intval', $finalScore);
            }
            if ($finalScores[0]['rewards'] === $finalScores[1]['rewards'] && $finalScores[0]['score'] === $finalScores[1]['score']) {
                $this->notify->all('log', clienttranslate('Multiple players are tied in the number of rewards. As they are also tied in the last round score, another round is played.'), []);
                $end = false;
            }
        }

        $table = $this->game->displayRoundResults($roundScores, $rewards);

        $scoreRound['table'] = $table;
        $scoreRound['end'] = $end;
        $this->game->setGlobalVariable(\ROUND_RESULT, $scoreRound);

        if ($end) {
            return EndRound::class;
        }

        $this->gamestate->setAllPlayersMultiactive();
        return null;
    }

    #[PossibleAction]
    public function actSeen(int $currentPlayerId) {
        $this->gamestate->setPlayerNonMultiactive($currentPlayerId, 'endRound');
    }

    public function zombie(int $playerId) {
        return $this->actSeen($playerId);
    }
}
