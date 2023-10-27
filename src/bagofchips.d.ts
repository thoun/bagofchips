/**
 * Your game interfaces
 */

interface Card {
    id: number;
    location: string;
    locationArg: number;
    type: number;
    subType: number;
    points: number;
    params: number[];
}

interface Chip {
    id: number;
    location: string;
    locationArg: number;
    color: number;
}

interface BagOfChipsPlayer extends Player {
    playerNo: number;
    rewards: number;
    
    hand?: Card[];

    minus: Card[];
    discard: Card[];
    plus: Card[];
}

interface RoundResult {
    cards: { [cardId: number]: [number, 'minus' | 'plus', number, number] };
    end: boolean;
    gameWon: boolean;
    instantWinner: number | null;
    table: { [playerId: number]: number[] };
}

interface BagOfChipsGamedatas {
    current_player_id: string;
    decision: {decision_type: string};
    game_result_neutralized: string;
    gamestate: Gamestate;
    gamestates: { [gamestateId: number]: Gamestate };
    neutralized_player_id: string;
    notifications: {last_packet_id: string, move_nbr: string}
    playerorder: (string | number)[];
    players: { [playerId: number]: BagOfChipsPlayer };
    tablespeed: string;

    // Add here variables you set up in getAllDatas
    chips: Chip[];
    roundResult: RoundResult;
}

interface BagOfChipsGame extends Game {
    cardsManager: CardsManager;
    chipsManager: ChipsManager;

    getPlayerId(): number;
    getPlayer(playerId: number): BagOfChipsPlayer;
    getGameStateName(): string;
    getCurrentPlayerTable(): PlayerTable | null;

    setTooltip(id: string, html: string): void;
    onHandCardSelectionChange(card: Card[]): void;
}

// discardCards
interface NotifDiscardCardsArgs {
    playerId: number;
    discard: Card[];
}

// placeCards
interface NotifPlaceCardsArgs {
    playerId: number;
    minus: Card[];
    plus: Card[];
}

// newHand
interface NotifNewHandArgs {
    cards: Card[];
}

// revealChips
interface NotifRevealChipsArgs {
    slot: number;
    chips: Chip[];
}

// scoreCard
interface NotifScoreCardArgs {
    playerId: number;
    card: Card;
    score: number;
    side: 'minus' | 'plus';
}

// rewards
interface NotifRewardsArgs {
    playerId: number;
    newScore: number;
}

// endRound
interface NotifEndRoundArgs {
}
