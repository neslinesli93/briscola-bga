/**
 *------
 * BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
 * BriscolaSuperamici implementation : © Tommaso Pifferi <p.tommy93@gmail.com> & Antonio <ai6chr+briscola@gmail.com>
 *
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 *
 * briscolasuperamici.js
 *
 * BriscolaSuperamici user interface script
 * 
 * In this file, you are describing the logic of your user interface, in Javascript language.
 *
 */

define([
    "dojo","dojo/_base/declare",
    "ebg/core/gamegui",
    "ebg/counter",
    "ebg/stock"
],
function (dojo, declare) {
    return declare("bgagame.briscolasuperamici", ebg.core.gamegui, {
        constructor: function(){
            this.cardwidth = 72;
            this.cardheight = 96;
        },
        
        /*
            setup:
            
            This method must set up the game user interface according to current game situation specified
            in parameters.
            
            The method is called each time the game interface is displayed to a player, ie:
            _ when the game starts
            _ when a player refreshes the game page (F5)
            
            "gamedatas" argument contains all datas retrieved by your "getAllDatas" PHP method.
        */
        
        setup: function(gamedatas)
        {
            var playersCount = 0;
            for (var k in gamedatas.players) {
                if (gamedatas.players.hasOwnProperty(k)) {
                    playersCount++;
                }
            }

            // Player hand
            this.playerHand = this.createDeck('myhand', false);
            // Set up card handlers
            dojo.connect(this.playerHand, 'onChangeSelection', this, 'onPlayerHandSelectionChanged');

            // Create a custom deck just to hold briscola
            this.briscolaCard = this.createDeck('briscola_wrap', false);

            // Cards in player's hand
            for (var i in this.gamedatas.hand) {
                var card = this.gamedatas.hand[i];
                var color = card.type;
                var value = card.type_arg;
                this.playerHand.addToStockWithId(this.getCardUniqueId(color, value), card.id);
            }

            // Cards played on table
            for (var j in this.gamedatas.cardsontable) {
                var card = this.gamedatas.cardsontable[j];
                var color = card.type;
                var value = card.type_arg;
                var playerId = card.location_arg;
                this.playCardOnTable(playerId, color, value, card.id);
            }

            // Show deck on the table
            this.buildDeckOnTable(gamedatas);
            this.addTooltip('mydeck_wrap', _('Deck with cards to be drawn'), '');

            // Add dealer
            this.setDealer(gamedatas.dealer);
            this.addTooltip('dealer_icon', _('Dealer for this hand'), '');

            // Add tooltips
            this.addTooltipToClass('playertablecard', _("Card played on the table"), '');
            this.addTooltip('briscola_wrap', _("Briscola card (to be drawn when the deck is empty)"), '');

            // Hide orientation icon if 2 players only
            if (playersCount === 2) {
                dojo.style('orientation', 'display', 'none');
            }

            // Setup game notifications to handle (see "setupNotifications" method below)
            this.setupNotifications();
        },
       

        ///////////////////////////////////////////////////
        //// Game & client states
        
        // onEnteringState: this method is called each time we are entering into a new game state.
        //                  You can use this method to perform some user interface changes at this moment.
        //
        onEnteringState: function(stateName, args) {
            console.log('Entering state: ' + stateName);

            switch(stateName) {
                case 'showCards':
                    console.log('I am now in showCards state');
                    console.log(args);
                    break;
            }
        },

        // onLeavingState: this method is called each time we are leaving a game state.
        //                 You can use this method to perform some user interface changes at this moment.
        //
        onLeavingState: function(stateName) {
            console.log('Leaving state: ' + stateName);
            
            switch(stateName) {
                case 'dummmy':
                    break;
            }               
        }, 

        // onUpdateActionButtons: in this method you can manage "action buttons" that are displayed in the
        //                        action status bar (ie: the HTML links in the status bar).
        //        
        onUpdateActionButtons: function(stateName, args) {
            console.log('onUpdateActionButtons: ' + stateName);
                      
            if(this.isCurrentPlayerActive()) {
                switch(stateName) {
                    case 'showCards':
                        this.teammateHand = args._private.teammate_hand;
                        this.teamWonTricks = args._private.trickswon;

                        this.addActionButton('showTeammateCards_button', _('Show teammate cards'), 'onClickShowTeammateCards');
                        this.addActionButton('showTeamTricks_button', _('Show all tricks won by your team'), 'onClickShowTeamWonTricks');
                        this.addActionButton('showGoToNextTurn_button', _('Go to next turn'), 'onClickGoToNextTurn');

                        break;
                }
            }
        },        

        ///////////////////////////////////////////////////
        //// Utility methods

        createDeck: function(domId, weighted) {
            var deckObject = new ebg.stock();
            deckObject.create(this, $(domId), this.cardwidth, this.cardheight);
            deckObject.image_items_per_row = 10;

            // Create cards types
            for (var color = 1; color <= 4; color++) {
                for (var value = 2; value <= 11; value++) {
                    // Build card type id
                    var cardTypeId = this.getCardUniqueId(color, value);

                    if (weighted) {
                        // Assign card id as weight, so that they appear sorted inside a deck
                        deckObject.addItemType(cardTypeId, cardTypeId, g_gamethemeurl + 'img/cards.jpg', cardTypeId);
                    } else {
                        // Cards are not sorted, order is random (used e.g. in player's hand)
                        deckObject.addItemType(cardTypeId, 0, g_gamethemeurl + 'img/cards.jpg', cardTypeId);
                    }
                }
            }

            return deckObject;
        },

        getCardUniqueId : function(color, value) {
            return (color - 1) * 10 + (value - 2);
        },

        playCardOnTable : function(playerId, color, value, cardId) {
            // player_id => direction
            dojo.place(this.format_block('jstpl_cardontable', {
                x : this.cardwidth * (value - 2),
                y : this.cardheight * (color - 1),
                player_id : playerId
            }), 'playertablecard_' + playerId);

            if (playerId != this.player_id) {
                // Some opponent played a card
                // Move card from player panel
                this.placeOnObject('cardontable_' + playerId, 'overall_player_board_' + playerId);
            } else {
                // You played a card. If it exists in your hand, move card from there and remove corresponding item
                if ($('myhand_item_' + cardId)) {
                    this.placeOnObject('cardontable_' + playerId, 'myhand_item_' + cardId);
                    this.playerHand.removeFromStockById(cardId);
                }
            }

            // In any case: move it to its final destination
            this.slideToObject('cardontable_' + playerId, 'playertablecard_' + playerId).play();
        },

        buildDeckOnTable: function(data) {
            // Create as many deck cards as there are cards to be drawn
            for (var k = 1; k <= data.cardsindeck; k++) {
                dojo.place(this.format_block('jstpl_mydeck', {
                    deckid: k
                }), 'mydeck_wrap');
            }

            // Add deck's remaining cards label
            if (data.cardsindeck) {
                dojo.place(this.format_block('jstpl_remaining_cards', {
                    remainingcards: data.cardsindeck
                }), 'remainingcards_wrap');
            }

            // Finally, add briscola if present
            var briscola = data.briscola;
            if (briscola) {
                var color = briscola.type;
                var value = briscola.type_arg;
                this.briscolaCard.addToStockWithId(this.getCardUniqueId(color, value), briscola.id);
            }
        },

        setDealer: function(playerId) {
            // Slide into position (bottom right of this player play zone)
            this.slideToObjectPos('dealer_icon', 'playertablecard_' + playerId, 78, 78, 1000).play();
        },

        ///////////////////////////////////////////////////
        //// Player's action

        onPlayerHandSelectionChanged : function() {
            var items = this.playerHand.getSelectedItems();

            if (items.length > 0) {
                var action = 'playCard';
                if (this.checkAction(action)) {
                    // Can play a card
                    var cardId = items[0].id;
                    this.ajaxcall("/" + this.game_name + "/" + this.game_name + "/" + action + ".html", {
                        id : cardId,
                        lock : true
                    }, this, function(result) {
                    }, function(is_error) {
                    });

                    this.playerHand.unselectAll();
                } else {
                    this.playerHand.unselectAll();
                }
            }
        },

        onClickShowTeammateCards: function() {
            // Prepare dialog to show cards
            var teammateCardsDialog = new dijit.Dialog({
                title: _("Your teammate hand"),
                onCancel: function() {
                    dojo.destroy('teammate-hand');
                }
            });

            var html = '<div id="teammate-hand"></div>';
            teammateCardsDialog.attr("content", html);
            teammateCardsDialog.show();

            // Build temporary deck and add cards do it
            var teammateCardsDeck = this.createDeck('teammate-hand', true);
            for (var i in this.teammateHand) {
                var card = this.teammateHand[i];
                var color = card.type;
                var value = card.type_arg;
                teammateCardsDeck.addToStockWithId(this.getCardUniqueId(color, value), card.id);
            }
        },

        onClickShowTeamWonTricks: function() {
            // Prepare dialog to show cards
            var teamTricksWonDialog = new dijit.Dialog({
                title: _("Tricks won by your team"),
                onCancel: function() {
                    dojo.destroy('team-tricks-won');
                }
            });

            var html = '<div id="team-tricks-won"></div>';
            teamTricksWonDialog.attr("content", html);
            teamTricksWonDialog.show();

            // Build temporary deck and add cards do it
            var teamTricksWonDeck = this.createDeck('team-tricks-won', true);
            for (var i in this.teamWonTricks) {
                var card = this.teamWonTricks[i];
                var color = card.type;
                var value = card.type_arg;
                teamTricksWonDeck.addToStockWithId(this.getCardUniqueId(color, value), card.id);
            }
        },

        onClickGoToNextTurn: function() {
            var action = 'endShowCards';
            if (this.checkAction(action)) {
                this.ajaxcall("/" + this.game_name + "/" + this.game_name + "/" + action + ".html", {
                    lock : true
                }, this, function(result) {
                }, function(is_error) {
                });
            }
        },
        
        ///////////////////////////////////////////////////
        //// Reaction to cometD notifications

        /*
            setupNotifications:
            
            In this method, you associate each of your game notifications with your local method to handle it.
            
            Note: game notification names correspond to "notifyAllPlayers" and "notifyPlayer" calls in
                  your briscolasuperamici.game.php file.
        
        */
        setupNotifications: function()
        {
            dojo.subscribe('dealCards', this, "notif_dealCards");
            dojo.subscribe('newHand', this, 'notif_newHand');
            dojo.subscribe('playCard', this, 'notif_playCard');

            dojo.subscribe('trickWin', this, 'notif_trickWin');
            this.notifqueue.setSynchronous('trickWin', 1000);
            dojo.subscribe('giveAllCardsToPlayer', this, 'notif_giveAllCardsToPlayer');
            dojo.subscribe('drawNewCard', this, 'notif_drawNewCard');

            dojo.subscribe('newScores', this, 'notif_newScores');
        },

        notif_dealCards: function(notif) {
            // Redundant, show the cards dealer
            this.setDealer(notif.args.player_id);
        },

        notif_newHand : function(notif) {
            // We received a new full hand of 3 cards.
            this.playerHand.removeAll();
            this.briscolaCard.removeAll();

            for (var i in notif.args.cards) {
                var card = notif.args.cards[i];
                var color = card.type;
                var value = card.type_arg;
                this.playerHand.addToStockWithId(this.getCardUniqueId(color, value), card.id);
            }

            // Show deck on table
            this.buildDeckOnTable(notif.args);
        },

        notif_playCard : function(notif) {
            // Play a card on the table
            this.playCardOnTable(notif.args.player_id, notif.args.color, notif.args.value, notif.args.card_id);
        },

        notif_trickWin : function(notif) {
            // Empty
        },

        notif_giveAllCardsToPlayer : function(notif) {
            // Move all cards on table to given table, then destroy them
            var winnerId = notif.args.player_id;
            for (var playerId in this.gamedatas.players) {
                var anim = this.slideToObject('cardontable_' + playerId, 'overall_player_board_' + winnerId);

                dojo.connect(anim, 'onEnd', function(node) {
                    dojo.destroy(node);
                });

                anim.play();
            }
        },

        notif_drawNewCard: function(notif) {
            var self = this;

            // Variables about the card's draw animation
            var deckIndexToPick = notif.args.deck_index_to_pick;
            var deckIndexToStartDeleteFrom = notif.args.deck_index_to_start_delete_from;
            var decksToDelete = notif.args.decks_to_delete;
            var remainingCardsDeckLabel = notif.args.remaining_cards_deck_label;
            var deleteBriscolaFromDeck = notif.args.delete_briscola_from_deck;

            // Variables about the drawn card itself
            var card = notif.args.card;
            var color = card.type;
            var value = card.type_arg;

            var idBriscola = notif.args.id_briscola;

            // Smooth animations and UX
            var briscolaCardAlreadyRemoved = false;
            var deckCardsAlreadyDestroyed = false;
            var deckLabelAlreadyDestroyed = false;

            // If deck_index_to_pick is 0, the player needs to pick the briscola
            if (deckIndexToPick == 0) {
                // Remove the cards from deck
                deckCardsAlreadyDestroyed = true;
                for (var i = deckIndexToStartDeleteFrom; i > deckIndexToStartDeleteFrom - decksToDelete; i--) {
                    if (i === 0) {
                        break;
                    }

                    dojo.destroy('mydeck_' + i);
                }

                // Destroy deck count as well
                deckLabelAlreadyDestroyed = true;
                dojo.destroy('remainingcards');

                var anim = this.slideToObject('briscola_wrap_item_' + idBriscola, 'myhand');

            } else {
                var anim = this.slideToObject('mydeck_' + deckIndexToPick, 'myhand');

                if (deleteBriscolaFromDeck) {
                    // We are on last hand, so remove briscola card and destroy deck label
                    self.briscolaCard.removeAll();
                    briscolaCardAlreadyRemoved = true;

                    dojo.destroy('remainingcards');
                    deckLabelAlreadyDestroyed = true;
                }
            }

            dojo.connect(anim, 'onEnd', function(node) {
                dojo.destroy(node);

                // Remove the cards from deck
                if (!deckCardsAlreadyDestroyed) {
                    for (var i = deckIndexToStartDeleteFrom; i > deckIndexToStartDeleteFrom - decksToDelete; i--) {
                        if (i === 0) {
                            if (!briscolaCardAlreadyRemoved) {
                                self.briscolaCard.removeAll();
                            }

                            break;
                        }

                        dojo.destroy('mydeck_' + i);
                    }
                }

                // Check if deck label with cards count still needs to be destroyed
                if (!deckLabelAlreadyDestroyed) {
                    dojo.destroy('remainingcards');
                }

                // Update remaining cards of the deck
                if (remainingCardsDeckLabel > 0) {
                    dojo.place(self.format_block('jstpl_remaining_cards', {
                        remainingcards: remainingCardsDeckLabel
                    }), 'remainingcards_wrap');
                }

                self.playerHand.addToStockWithId(self.getCardUniqueId(color, value), card.id);
            });

            anim.play();
        },

        notif_newScores : function(notif) {
            // Update players' scores
            for (var playerId in notif.args.newScores) {
                this.scoreCtrl[playerId].toValue(notif.args.newScores[playerId]);
            }
        },
   });             
});
