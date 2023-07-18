class CardsManager extends CardManager<Card> {
    constructor (public game: BagOfChipsGame) {
        super(game, {
            getId: (card) => `card-${card.id}`,
            setupDiv: (card: Card, div: HTMLElement) => {
                div.classList.add('objective');
            },
            setupFrontDiv: (card: Card, div: HTMLElement) => { 
                div.dataset.type = ''+card.type;
                div.dataset.subType = ''+card.subType;
                game.setTooltip(div.id, this.getTooltip(card));
            },
            isCardVisible: card => Boolean(card.type),
            cardWidth: 200,
            cardHeight: 280,
        });
    }

    private getTooltip(card: Card): string {
        let message = `TODO`;/*
        <strong>${_("Color:")}</strong> ${this.game.getTooltipColor(card.color)}
        <br>
        <strong>${_("Gain:")}</strong> <strong>1</strong> ${this.game.getTooltipGain(card.gain)}
        `;*/
 
        return message;
    }
    
    public getHtml(card: Card): string {
        let html = `<div class="card objective" data-side="front">
            <div class="card-sides">
                <div class="card-side front" data-type="${card.type}" data-sub-type="${card.subType}">
                </div>
            </div>
        </div>`;
        return html;
    }
}