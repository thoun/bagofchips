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

    public getPower(type: number, number?: string | number): string {
        switch (type) {
            case 1: return _("This Objective is completed if at the end of the round there is at least ${number} chip(s) of each flavor on the Board Cards.").replace('${number}', number);
            case 2: return _("This Objective is completed if at the end of the round, the displayed combination appears on the Board Cards. If there are more chips on the Board Cards than indicated on the Objective Card, the Objective is completed.");
            case 3: return _("This Objective is completed if at the end of the round there are as many chips of each of the two displayed flavors on the Board Cards.");
            case 4: return _("This Objective is completed if the <strong>last</strong> chip of the round to be placed on the Board Card matches the displayed flavor.");
            case 5: return _("This Objective is completed if at the end of the round there is no chip of the displayed flavor on the Board Cards.");
            case 6: return _("This Objective is completed if at the end of the round there is at least one chip of the displayed flavor on the Board Cards. This Objective is worth the number of points indicated multiplied by the number of chips of the matching flavor.");
            case 7: return _("This Objective is completed if at the end of the round, there is more (+) chips than (-) chips on the Board Cards.");
            case 8: return this.getPower(7) + '<br><br>' + formatTextIcons(_("However, if it is completed while the card is on a player’s [+] side, that player immediately <strong>wins the game</strong> (and not just the current round!). If the Objective is completed while the card is on [-] the side of a player, that player automatically loses the round, regardless of their score."));
        }
    }

    private getTooltip(card: Card): string {
        if (card.type == 7 && card.subType == 7) {
            return this.getPower(8);
        } else {
            return `
                <strong>${_("Points:")}</strong> ${card.type == 6 ? _("${points} / matching chip").replace('${points}', card.points) : card.points}
                <br><br>
                ${this.getPower(card.type, card.type == 1 ? card.subType : undefined)}
            `;
        }
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