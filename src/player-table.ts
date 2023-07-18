const isDebug = window.location.host == 'studio.boardgamearena.com' || window.location.hash.indexOf('debug') > -1;;
const log = isDebug ? console.log.bind(window.console) : function () { };

class PlayerTable {
    public playerId: number;
    public voidStock: VoidStock<Card>;
    public hand?: LineStock<Card>;
    public minus: SlotStock<Card>;
    public discard: Deck<Card>;
    public plus: SlotStock<Card>;

    private currentPlayer: boolean;

    constructor(private game: BagOfChipsGame, player: BagOfChipsPlayer) {
        this.playerId = Number(player.id);
        this.currentPlayer = this.playerId == this.game.getPlayerId();

        let html = `
        <div id="player-table-${this.playerId}" class="player-table" style="--player-color: #${player.color};">
            <div id="player-table-${this.playerId}-name" class="name-wrapper">${player.name}</div>
        `;
        if (this.currentPlayer) {
            html += `
            <div class="block-with-text hand-wrapper">
                <div class="block-label">${_('Your hand')}</div>
                <div id="player-table-${this.playerId}-hand" class="hand cards"></div>
            </div>`;
        }
        html += `
            <div class="player-visible-cards">
                <div id="player-table-${this.playerId}-minus" class="minus"></div>
                <div id="player-table-${this.playerId}-discard" class="discard-cards"></div>
                <div id="player-table-${this.playerId}-plus" class="plus"></div>
            </div>
        </div>
        `;

        dojo.place(html, document.getElementById('tables'));

        if (this.currentPlayer) {
            const handDiv = document.getElementById(`player-table-${this.playerId}-hand`);
            this.hand = new LineStock<Card>(this.game.cardsManager, handDiv, {
                sort: (a: Card, b: Card) => a.points - b.points,
            });
            this.hand.onSelectionChange = (selection: Card[]) => this.game.onHandCardSelectionChange(selection);            
            this.hand.addCards(player.hand);

        }
        this.voidStock = new VoidStock<Card>(this.game.cardsManager, document.getElementById(`player-table-${this.playerId}-name`));

        
        this.minus = new SlotStock<Card>(this.game.cardsManager, document.getElementById(`player-table-${this.playerId}-minus`), {
            slotsIds: [0],
        });
        player.minus.forEach((card, index) => this.minus.addCard(card, undefined, { slot: index }));
        this.discard = new Deck<Card>(this.game.cardsManager, document.getElementById(`player-table-${this.playerId}-discard`), {
            topCard: player.discard[0],
            cardNumber: player.discard.length,
        });
        this.plus = new SlotStock<Card>(this.game.cardsManager, document.getElementById(`player-table-${this.playerId}-plus`), {
            slotsIds: [0, 1],
        });
        player.plus.forEach((card, index) => this.plus.addCard(card, undefined, { slot: index }));
    }

    public setHandSelectable(selectable: boolean) {
        this.hand.setSelectionMode(selectable ? 'multiple' : 'none');
    }

    public discardCards(discard: Card[]): Promise<any> {
        return this.discard.addCards(discard, { fromStock: this.currentPlayer ? this.hand : this.voidStock });
    }

    public placeCards(minus: Card[], plus: Card[]): Promise<any> {
        return Promise.all([
            ...minus.map((card, index) => this.minus.addCard(card, { fromStock: this.currentPlayer ? this.hand : this.voidStock }, { slot: index })),
            ...plus.map((card, index) => this.plus.addCard(card, { fromStock: this.currentPlayer ? this.hand : this.voidStock }, { slot: index })),
        ]);
    }
    
    public newHand(cards: Card[]): Promise<any> {
        return this.hand.addCards(cards, { fromStock: this.voidStock });
    }

    public scoreCard(card: Card, score: number) {
        (this.game as any).displayScoring(this.game.cardsManager.getId(card), this.game.getPlayer(this.playerId).color, score, 1000);
    }
    
    public endRound(): Promise<any> { 
        return this.voidStock.addCards([
            ...(this.hand?.getCards() ?? []),
            ...this.minus.getCards(),
            ...this.discard.getCards(),
            ...this.plus.getCards(),
        ]);
    }
}