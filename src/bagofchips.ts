declare const define;
declare const ebg;
declare const $;
declare const dojo: Dojo;
declare const _;
declare const g_gamethemeurl;
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

class BagOfChips implements BagOfChipsGame {
    public cardsManager: CardsManager;
    public chipsManager: ChipsManager;

    private zoomManager: ZoomManager;
    private animationManager: AnimationManager;
    private gamedatas: BagOfChipsGamedatas;
    private tableCenter: TableCenter;
    private playersTables: PlayerTable[] = [];
    
    private TOOLTIP_DELAY = document.body.classList.contains('touch-device') ? 1500 : undefined;

    constructor() {
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
        const code = CODES[(this as any).prefs[202].value] ?? this.getCodeByLanguage();
        //document.getElementById(`table`).insertAdjacentHTML(`beforebegin`, `<link id="code-stylesheet" rel="stylesheet" type="text/css" href="${g_gamethemeurl}img/${code}/skin.css"/>`);
        
        g_img_preload.push(...[
            `${code}/card-back.png`,
            `${code}/card-repartition.png`,
            ...[1,2,3,4,5,6,7].map(type => `${code}/cards${type}.png`),
            `${code}/chips.png`,
            `${code}/icons.png`,
            `${code}/maps.png`,
        ]);
        /* TODO if (!gamedatas.variantOption) {
            (this as any).dontPreloadImage('artefacts.jpg');
        }
        if (gamedatas.boatSideOption == 2) {
            (this as any).dontPreloadImage('boats-normal.png');
        } else {
            (this as any).dontPreloadImage('boats-advanced.png');
        }*/

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
        this.setupPreferences();

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
        if ((this as any).isCurrentPlayerActive()) {
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
        
        if ((this as any).isCurrentPlayerActive()) {
            switch (stateName) {
                case 'discardCards':
                    this.onEnteringSelectCards();
                    (this as any).addActionButton(`discardCards_button`, '', () => this.discardCards());
                    this.onHandCardSelectionChange(this.getCurrentPlayerTable().hand?.getSelection());
                    break;
                case 'placeCards':
                    this.onEnteringSelectCards();
                    (this as any).addActionButton(`placeMinus_button`, '', () => this.placeCards());
                    this.onHandCardSelectionChange(this.getCurrentPlayerTable().hand?.getSelection());
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
        (this as any).addTooltipHtml(id, html, this.TOOLTIP_DELAY);
    }
    public setTooltipToClass(className: string, html: string) {
        (this as any).addTooltipHtmlToClass(className, html, this.TOOLTIP_DELAY);
    }

    public getPlayerId(): number {
        return Number((this as any).player_id);
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

    private setupPreferences() {
        // Extract the ID and value from the UI control
        const onchange = (e) => {
          var match = e.target.id.match(/^preference_[cf]ontrol_(\d+)$/);
          if (!match) {
            return;
          }
          var prefId = +match[1];
          var prefValue = +e.target.value;
          (this as any).prefs[prefId].value = prefValue;
          this.onPreferenceChange(prefId, prefValue);
        }
        
        // Call onPreferenceChange() when any value changes
        dojo.query(".preference_control").connect("onchange", onchange);
        
        // Call onPreferenceChange() now
        dojo.forEach(
          dojo.query("#ingame_menu_content .preference_control"),
          el => onchange({ target: el })
        );
    }
      
    private onPreferenceChange(prefId: number, prefValue: number) {
        switch (prefId) {
            case 202:
                const code = CODES[prefValue] ?? this.getCodeByLanguage();
                document.getElementById(`code-stylesheet`)?.remove();
                document.getElementById(`table`).insertAdjacentHTML(`beforebegin`, `<link id="code-stylesheet" rel="stylesheet" type="text/css" href="${g_gamethemeurl}img/${code}/skin.css"/>`);
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
        const playerIndex = players.findIndex(player => Number(player.id) === Number((this as any).player_id));
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
                <div>${this.cardsManager.getPower(i)}</div>
            </div> `;
        }
        html += `</div>`;

        return html;
    }
    
    public discardCards() {
        if(!(this as any).checkAction('discardCards')) {
            return;
        }

        const ids = this.getCurrentPlayerTable().hand.getSelection().map(card => card.id);

        this.takeAction('discardCards', {
            ids: ids.join(','),
        });
    }
    
    public placeCards() {
        if(!(this as any).checkAction('placeCards')) {
            return;
        }

        const ids = this.getCurrentPlayerTable().hand.getSelection().map(card => card.id);
        const others = this.getCurrentPlayerTable().hand.getCards().filter(card => !ids.includes(card.id)).map(card => card.id);


        this.takeAction('placeCards', {
            minus: ids.join(','),
            plus: others.join(','),
        });
    }

    public takeAction(action: string, data?: any) {
        data = data || {};
        data.lock = true;
        (this as any).ajaxcall(`/bagofchips/bagofchips/${action}.html`, data, this, () => {});
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
            ['discardCards', undefined],
            ['placeCards', undefined],
            ['newHand', undefined],
            ['revealChips', undefined],
            ['scoreCard', ANIMATION_MS * 2.5],
            ['rewards', 1],
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

    notif_endRound() {
        return Promise.all([
            this.tableCenter.endRound(),
            ...this.playersTables.map(table => table.endRound()),
        ]);
    }
    

    /* This enable to inject translatable styled things to logs or action bar */
    /* @Override */
    public format_string_recursive(log: string, args: any) {
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
        return (this as any).inherited(arguments);
    }
}