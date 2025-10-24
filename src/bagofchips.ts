declare const g_img_preload;

const ANIMATION_MS = 500;
const ACTION_TIMER_DURATION = 5;

const LOCAL_STORAGE_ZOOM_KEY = 'BagOfChips-zoom';
const LOCAL_STORAGE_JUMP_TO_FOLDED_KEY = 'BagOfChips-jump-to-folded';
const LOCAL_STORAGE_HELP_FOLDED_KEY = 'BagOfChips-help-folded';

const CODES = [
    null,
    'us',
    'de',
    'ca',
];

function formatTextIcons(str: string) {
    return str.replace(/\[\-\]/g, '<div class="minus icon"></div>').replace(/\[\+\]/g, '<div class="plus icon"></div>');
}

// @ts-ignore
GameGui = (function () { // this hack required so we fake extend GameGui
  function GameGui() {}
  return GameGui;
})();

class BagOfChips extends GameGui<BagOfChipsGamedatas> implements BagOfChipsGame {
    public cardsManager: CardsManager;
    public chipsManager: ChipsManager;

    private zoomManager: ZoomManager;
    private animationManager: AnimationManager;
    public gamedatas: BagOfChipsGamedatas;
    private tableCenter: TableCenter;
    private playersTables: PlayerTable[] = [];
    
    private TOOLTIP_DELAY = document.body.classList.contains('touch-device') ? 1500 : undefined;

    constructor() {
        super();
    }
    
    /*
        setup:

        This method must set up the game user interface according to current game situation specified
        in parameters.

        The method is called each time the game interface is displayed to a player, ie:
        _ when the game starts
        _ when a player refreshes the game page (F5)

        "gamedatas" argument contains all datas retrieved by your "getAllDatas" PHP method.
    */

    public setup(gamedatas: BagOfChipsGamedatas) {
        this.getGameAreaElement().insertAdjacentHTML('beforeend', `
            <link rel="stylesheet" href="https://use.typekit.net/jim0ypy.css">

            <div id="result"></div>

            <div id="table">
                <div id="tables-and-center">
                    <div id="table-center-wrapper">
                        <div id="table-center">
                            <div id="bag"></div>
                        </div>
                    </div>
                    <div id="tables"></div>
                </div>
            </div>

            <div id="skin"></div>
        `);

        const code = CODES[this.getGameUserPreference(202)] ?? this.getCodeByLanguage();
        //document.getElementById(`table`).insertAdjacentHTML(`beforebegin`, `<link id="code-stylesheet" rel="stylesheet" type="text/css" href="${g_gamethemeurl}img/${code}/skin.css"/>`);
        
        g_img_preload.push(...[
            `${code}/card-back.png`,
            `${code}/card-repartition.png`,
            ...[1,2,3,4,5,6,7].map(type => `${code}/cards${type}.png`),
            `${code}/chips.png`,
            `${code}/icons.png`,
            `${code}/maps.png`,
        ]);

        log( "Starting game setup" );
        
        this.gamedatas = gamedatas;

        log('gamedatas', gamedatas);


        this.cardsManager = new CardsManager(this);
        this.chipsManager = new ChipsManager(this);        
        this.animationManager = new AnimationManager(this);
        new JumpToManager(this, {
            localStorageFoldedKey: LOCAL_STORAGE_JUMP_TO_FOLDED_KEY,
            topEntries: [
                new JumpToEntry(_('Main board'), 'table-center', { 'color': '#a91216' })
            ],
            entryClasses: 'round-point',
            defaultFolded: true,
        });

        this.tableCenter = new TableCenter(this, gamedatas);
        this.createPlayerPanels(gamedatas);
        this.createPlayerTables(gamedatas);
        
        this.zoomManager = new ZoomManager({
            element: document.getElementById('table'),
            smooth: false,
            zoomControls: {
                color: 'black',
            },
            localStorageZoomKey: LOCAL_STORAGE_ZOOM_KEY,
            onDimensionsChange: () => {
                const tablesAndCenter = document.getElementById('tables-and-center');
                const clientWidth = tablesAndCenter.clientWidth;
                tablesAndCenter.classList.toggle('double-column', clientWidth > 1680); // 830px + 20px + 830px
            },
        });

        new HelpManager(this, { 
            buttons: [
                new BgaHelpPopinButton({
                    title: _("Objective cards").toUpperCase(),
                    html: this.getHelpHtml(),
                    buttonBackground: '#a91216',
                }),
                new BgaHelpExpandableButton({
                    expandedWidth: '200px',
                    expandedHeight: '280px',
                    defaultFolded: false,
                    localStorageFoldedKey: LOCAL_STORAGE_HELP_FOLDED_KEY
                }),
            ]
        });
        this.setupNotifications();

        if (gamedatas.roundResult) {
            Object.entries(gamedatas.roundResult.cards).forEach(([cardId, result]) => {
                const id = Number(cardId);
                const playerTable = this.getPlayerTable(result[0]);
                const card = playerTable.plus.getCards().find(card => card.id == id) ?? playerTable.minus.getCards().find(card => card.id == id);
                if (card) {
                    this.getPlayerTable(result[0]).scoreCard(card, result[2], result[1]);
                }
            });
            this.setRoundResult(gamedatas.roundResult.table);
        }

        let html = `<h3 class="title">${_("Skin")}</h3>
        <div class="buttons">`;
        CODES.filter(Boolean).forEach(code => html += `<button id="set-skin-${code}" class="bgabutton bgabutton_gray skin-button" style="background-image: url('${g_gamethemeurl}img/skin-${code}.png');"></button>`);
        html += `</div>`;
        document.getElementById('skin').insertAdjacentHTML('beforeend', html);
        CODES.filter(Boolean).forEach(code => document.getElementById(`set-skin-${code}`).addEventListener('click', () => this.changeSkin(code)));

        log( "Ending game setup" );
    }

    ///////////////////////////////////////////////////
    //// Game & client states

    // onEnteringState: this method is called each time we are entering into a new game state.
    //                  You can use this method to perform some user interface changes at this moment.
    //
    public onEnteringState(stateName: string, args: any) {
        log('Entering state: ' + stateName, args.args);
    }

    private onEnteringSelectCards() {
        if (this.isCurrentPlayerActive()) {
            this.getCurrentPlayerTable().setHandSelectable(true);
        }
    }

    public onLeavingState(stateName: string) {
        log( 'Leaving state: '+stateName );

        switch (stateName) {
            case 'discardCards':
            case 'placeCards':
                this.onLeavingSelectCards();
                break;
        }
    }

    private onLeavingSelectCards() {
        this.getCurrentPlayerTable()?.setHandSelectable(false);
    }

    // onUpdateActionButtons: in this method you can manage "action buttons" that are displayed in the
    //                        action status bar (ie: the HTML links in the status bar).
    //
    public onUpdateActionButtons(stateName: string, args: any) {
        
        if (this.isCurrentPlayerActive()) {
            switch (stateName) {
                case 'discardCards':
                    this.onEnteringSelectCards();
                    this.statusBar.addActionButton('', () => this.discardCards(), { id: `discardCards_button` });
                    this.onHandCardSelectionChange(this.getCurrentPlayerTable().hand?.getSelection());
                    break;
                case 'placeCards':
                    this.onEnteringSelectCards();
                    this.statusBar.addActionButton('', () => this.placeCards(), { id: `placeMinus_button` });
                    this.onHandCardSelectionChange(this.getCurrentPlayerTable().hand?.getSelection());
                    break;
                case 'beforeEndRound':
                    this.statusBar.addActionButton(_("Seen"), () => this.bgaPerformAction('actSeen'));
                    break;
            }
        }
    }    
    
    public onHandCardSelectionChange(selection: Card[]): void {
        if (this.gamedatas.gamestate.name == 'discardCards') {
            const label = _('Discard ${number} selected cards').replace('${number}', `${selection.length}`);

            const valid = selection.length == +this.gamedatas.gamestate.args.number;
            const button = document.getElementById('discardCards_button');
            button.innerHTML = label;
            button.classList.toggle('disabled', !valid);

            this.getCurrentPlayerTable().hand.setSelectableCards(valid ? selection : undefined);
        } else if (this.gamedatas.gamestate.name == 'placeCards') {
            const valid = selection.length == 1;
            const minusLabel = formatTextIcons(_('Set selected card on [-] side'));

            const minusButton = document.getElementById('placeMinus_button');
            minusButton.innerHTML = minusLabel;
            minusButton.classList.toggle('disabled', !valid);
            this.getCurrentPlayerTable().hand.setSelectableCards(valid ? selection : undefined);
        }
    }

    ///////////////////////////////////////////////////
    //// Utility methods


    ///////////////////////////////////////////////////

    public setTooltip(id: string, html: string) {
        this.addTooltipHtml(id, html, this.TOOLTIP_DELAY);
    }
    public setTooltipToClass(className: string, html: string) {
        this.addTooltipHtmlToClass(className, html, this.TOOLTIP_DELAY);
    }

    public getPlayerId(): number {
        return Number(this.player_id);
    }

    public getPlayer(playerId: number): BagOfChipsPlayer {
        return Object.values(this.gamedatas.players).find(player => Number(player.id) == playerId);
    }

    private getPlayerTable(playerId: number): PlayerTable {
        return this.playersTables.find(playerTable => playerTable.playerId === playerId);
    }

    public getCurrentPlayerTable(): PlayerTable | null {
        return this.playersTables.find(playerTable => playerTable.playerId === this.getPlayerId());
    }

    public getGameStateName(): string {
        return this.gamedatas.gamestate.name;
    }

    private changeSkin(code: string) {
        const value = CODES.indexOf(code);

        [
            document.getElementById(`preference_control_202`), 
            document.getElementById(`preference_fontrol_202`)
        ].forEach((control: HTMLSelectElement) => control.value = ''+value);  

        //this.applySkin(code);     
        document.getElementById(`preference_control_202`).dispatchEvent(new Event('change'));
    }

    private applySkin(code: string) {
        document.getElementById(`code-stylesheet`)?.remove();
        document.getElementById(`table`).insertAdjacentHTML(`beforebegin`, `<link id="code-stylesheet" rel="stylesheet" type="text/css" href="${g_gamethemeurl}img/${code}/skin.css"/>`);
    }
      
    // @ts-ignore
    public onGameUserPreferenceChanged(prefId: number, prefValue: number) {
        switch (prefId) {
            case 202:
                const code = CODES[prefValue] ?? this.getCodeByLanguage();
                this.applySkin(code);
                break;
        }
    }
    
    private getCodeByLanguage(): string {
        switch ((window as any).dataLayer[0].user_lang) {
            case 'en': return 'us';
            case 'de': return 'de';

            default: return 'us';
        }
    }

    private getOrderedPlayers(gamedatas: BagOfChipsGamedatas) {
        const players = Object.values(gamedatas.players).sort((a, b) => a.playerNo - b.playerNo);
        const playerIndex = players.findIndex(player => Number(player.id) === Number(this.player_id));
        const orderedPlayers = playerIndex > 0 ? [...players.slice(playerIndex), ...players.slice(0, playerIndex)] : players;
        return orderedPlayers;
    }

    private createPlayerPanels(gamedatas: BagOfChipsGamedatas) {
        const players = Object.values(gamedatas.players);
        const maxRewards = players.length <=2 ? 3 : 4;

        players.forEach(player => {
            const playerId = Number(player.id);   

            let html = `<div class="counters">
            
                <div id="reward-counter-wrapper-${player.id}" class="reward-counter">`;
            for (let i = 0; i < Math.max(maxRewards, player.rewards); i++) {
                html += `<div class="reward icon ${i >= player.rewards ? 'grayed' : ''}"></div>`;
            }
            html += `    </div>
            </div>`;

            dojo.place(html, `player_board_${player.id}`);
        });

        this.setTooltipToClass('reward-counter', _('Rewards'));
    }

    private createPlayerTables(gamedatas: BagOfChipsGamedatas) {
        const orderedPlayers = this.getOrderedPlayers(gamedatas);

        orderedPlayers.forEach(player => 
            this.createPlayerTable(gamedatas, Number(player.id))
        );
    }

    private createPlayerTable(gamedatas: BagOfChipsGamedatas, playerId: number) {
        const table = new PlayerTable(this, gamedatas.players[playerId]);
        this.playersTables.push(table);
    }

    private setReward(playerId: number, count: number) {
        const tokens = Array.from(document.querySelectorAll(`#reward-counter-wrapper-${playerId} .reward`)) as HTMLElement[];
        tokens.forEach((token, index) => token.classList.toggle('grayed', index >= count));
        for (let i = tokens.length; i < count; i++) {
            document.getElementById(`reward-counter-wrapper-${playerId}`).insertAdjacentHTML('beforeend', `<div class="reward icon"></div>`);
        }
    }

    private getHelpHtml() {
        let html = `
        <div id="help-popin">
        `;

        for (let i = 1; i <= 8; i++) {
            html += `
            <div class="help-section">
                <div id="help-card-${i}">${this.cardsManager.getHtml({ type: Math.min(7, i), subType: i == 8 ? 7 : 1 } as Card)}</div>
                <div>${this.cardsManager.getPower(i, i == 1 ? 1 : undefined)}</div>
            </div> `;
        }
        html += `</div>`;

        return html;
    }
    
    public discardCards() {
        const ids = this.getCurrentPlayerTable().hand.getSelection().map(card => card.id);

        this.bgaPerformAction('actDiscardCards', {
            ids: ids.join(','),
        });
    }
    
    public placeCards() {
        const ids = this.getCurrentPlayerTable().hand.getSelection().map(card => card.id);
        const others = this.getCurrentPlayerTable().hand.getCards().filter(card => !ids.includes(card.id)).map(card => card.id);


        this.bgaPerformAction('actPlaceCards', {
            minus: ids.join(','),
            plus: others.join(','),
        });
    }

    ///////////////////////////////////////////////////
    //// Reaction to cometD notifications

    /*
        setupNotifications:

        In this method, you associate each of your game notifications with your local method to handle it.

        Note: game notification names correspond to "notifyAllPlayers" and "notifyPlayer" calls in
                your pylos.game.php file.

    */
    setupNotifications() {
        //log( 'notifications subscriptions setup' );

        const notifs = [
            ['wait1000', ANIMATION_MS],
            ['wait3000', ANIMATION_MS * 3],
            ['discardCards', undefined],
            ['placeCards', undefined],
            ['newHand', undefined],
            ['revealChips', undefined],
            ['scoreCard', ANIMATION_MS * 2.5],
            ['rewards', 1],
            ['showRoundResult', 1],
            ['endRound', undefined],
        ];
    
        notifs.forEach((notif) => {
            dojo.subscribe(notif[0], this, (notifDetails: Notif<any>) => {
                log(`notif_${notif[0]}`, notifDetails.args);

                const promise = this[`notif_${notif[0]}`](notifDetails.args);

                // tell the UI notification ends, if the function returned a promise
                promise?.then(() => (this as any).notifqueue.onSynchronousNotificationEnd());
            });
            (this as any).notifqueue.setSynchronous(notif[0], notif[1]);
        });

        if (isDebug) {
            notifs.forEach((notif) => {
                if (!this[`notif_${notif[0]}`]) {
                    console.warn(`notif_${notif[0]} function is not declared, but listed in setupNotifications`);
                }
            });

            Object.getOwnPropertyNames(BagOfChips.prototype).filter(item => item.startsWith('notif_')).map(item => item.slice(6)).forEach(item => {
                if (!notifs.some(notif => notif[0] == item)) {
                    console.warn(`notif_${item} function is declared, but not listed in setupNotifications`);
                }
            });
        }
    }

    notif_wait1000() {}
    notif_wait3000() {}

    notif_discardCards(args: NotifDiscardCardsArgs) {
        return this.getPlayerTable(args.playerId).discardCards(args.discard);
    }

    notif_placeCards(args: NotifPlaceCardsArgs) {
        return this.getPlayerTable(args.playerId).placeCards(args.minus, args.plus);
    }

    notif_newHand(args: NotifNewHandArgs) {
        return this.getCurrentPlayerTable().newHand(args.cards);
    }

    notif_revealChips(args: NotifRevealChipsArgs) {
        return this.tableCenter.revealChips(args.slot, args.chips);
    }

    notif_scoreCard(args: NotifScoreCardArgs) {
        this.getPlayerTable(args.playerId).scoreCard(args.card, args.score, args.side);
    }

    notif_rewards(args: NotifRewardsArgs) {
        this.setReward(args.playerId, args.newScore);
    }

    private setRoundResult(table: { [playerId: number]: number[] }) {
        const playersIds = Object.keys(table).map(Number);

        let html = `<table class='round-result'>
            <tr><th></th>${playersIds.map(playerId => `<td><strong style='color: #${this.getPlayer(playerId).color};'>${this.getPlayer(playerId).name}</strong></td>`).join('')}</tr>
            <tr><th>${_('Hand [-] points')}</th>${playersIds.map(playerId => `<td>${Math.abs(table[playerId][0]) > 999 ? '-' : table[playerId][0]}</td>`).join('')}</tr>
            <tr><th>${_('Hand [+] points')}</th>${playersIds.map(playerId => `<td>${table[playerId][1] > 999 ? '-' : table[playerId][1]}</td>`).join('')}</tr>
            <tr><th>${_('Hand total points')}</th>${playersIds.map(playerId => `<td>${table[playerId][2] > 999 ? '-' : table[playerId][2]}</td>`).join('')}</tr>
            <tr><th>${_('Hand rewards')}</th>${playersIds.map(playerId => `<td>${Array(table[playerId][3]).fill(0).map(() => `<div class="reward icon"></div>`).join('')}</td>`).join('')}</tr>
            <tr><th>${_('Total rewards')}</th>${playersIds.map(playerId => `<td>${Array(table[playerId][4]).fill(0).map(() => `<div class="reward icon"></div>`).join('')}</td>`).join('')}</tr>
        </table>`;

        document.getElementById(`result`).innerHTML = formatTextIcons(html);
    }

    notif_showRoundResult(args: { table: { [playerId: number]: number[] } }) {
        this.setRoundResult(args.table);
    }

    notif_endRound() {
        document.getElementById(`result`).innerHTML = ``;
        return Promise.all([
            this.tableCenter.endRound(),
            ...this.playersTables.map(table => table.endRound()),
        ]);
    }
    

    /* This enable to inject translatable styled things to logs or action bar */
    /* @Override */
    public bgaFormatText(log: string, args: any) {
        try {
            if (log && args && !args.processed) {
                if (args.chips_images === '' && args.chips) {
                    args.chips_images = `<div class="log-chip-image">${args.chips.map((chip: Chip) => this.chipsManager.getHtml(chip)).join(' ')}</div>`;
                }

                if (args.card_image === '' && args.card) {
                    args.card_image = `<div class="log-card-image">${this.cardsManager.getHtml(args.card)}</div>`;
                }

                for (const property in args) {
                    if (['number'].includes(property) && args[property][0] != '<') {
                        args[property] = `<strong>${_(args[property])}</strong>`;
                    }
                }

                log = formatTextIcons(_(log));
            }
        } catch (e) {
            console.error(log,args,"Exception thrown", e.stack);
        }
        return { log, args };
    }
}