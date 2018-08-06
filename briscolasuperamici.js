/**
 *------
 * BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
 * BriscolaSuperamici implementation : © <Your name here> <Your email address here>
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
        
        setup: function( gamedatas )
        {
            console.log('GAMEDATAS');
            console.log(gamedatas);

            // Player hand
            this.playerHand = new ebg.stock(); // new stock object for hand
            this.playerHand.create( this, $('myhand'), this.cardwidth, this.cardheight );

            // 10 images per row
            this.playerHand.image_items_per_row = 10;

            // Create a custom deck just to hold briscola
            var briscola = gamedatas.briscola;
            if (briscola) {
                this.briscolaCard = new ebg.stock(); // new stock object for hand
                this.briscolaCard.create( this, $('briscola_wrap'), this.cardwidth, this.cardheight );
                this.briscolaCard.image_items_per_row = 10;
            }

            // Create cards types:
            for (var color = 1; color <= 4; color++) {
                for (var value = 2; value <= 11; value++) {
                    // Build card type id
                    // N.B: Cards are not sorted when in player's hand! Order is just random
                    var card_type_id = this.getCardUniqueId(color, value);
                    this.playerHand.addItemType(card_type_id, 0, g_gamethemeurl + 'img/cards.jpg', card_type_id);

                    if (briscola) {
                        this.briscolaCard.addItemType(card_type_id, 0, g_gamethemeurl + 'img/cards.jpg', card_type_id);
                    }
                }
            }

            // Cards in player's hand
            for ( var i in this.gamedatas.hand) {
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
                var player_id = card.location_arg;
                this.playCardOnTable(player_id, color, value, card.id);
            }

            // Show deck on the table
            this.buildDeckOnTable(gamedatas);

            dojo.connect( this.playerHand, 'onChangeSelection', this, 'onPlayerHandSelectionChanged' );

            // Setup game notifications to handle (see "setupNotifications" method below)
            this.setupNotifications();

            console.log( "Ending game setup" );
        },
       

        ///////////////////////////////////////////////////
        //// Game & client states
        
        // onEnteringState: this method is called each time we are entering into a new game state.
        //                  You can use this method to perform some user interface changes at this moment.
        //
        onEnteringState: function( stateName, args )
        {
            console.log( 'Entering state: '+stateName );
            
            switch( stateName )
            {
            
            /* Example:
            
            case 'myGameState':
            
                // Show some HTML block at this game state
                dojo.style( 'my_html_block_id', 'display', 'block' );
                
                break;
           */
           
           
            case 'dummmy':
                break;
            }
        },

        // onLeavingState: this method is called each time we are leaving a game state.
        //                 You can use this method to perform some user interface changes at this moment.
        //
        onLeavingState: function( stateName )
        {
            console.log( 'Leaving state: '+stateName );
            
            switch( stateName )
            {
            
            /* Example:
            
            case 'myGameState':
            
                // Hide the HTML block we are displaying only during this game state
                dojo.style( 'my_html_block_id', 'display', 'none' );
                
                break;
           */
           
           
            case 'dummmy':
                break;
            }               
        }, 

        // onUpdateActionButtons: in this method you can manage "action buttons" that are displayed in the
        //                        action status bar (ie: the HTML links in the status bar).
        //        
        onUpdateActionButtons: function( stateName, args )
        {
            console.log( 'onUpdateActionButtons: '+stateName );
                      
            if( this.isCurrentPlayerActive() )
            {            
                switch( stateName )
                {
/*               
                 Example:
 
                 case 'myGameState':
                    
                    // Add 3 action buttons in the action status bar:
                    
                    this.addActionButton( 'button_1_id', _('Button 1 label'), 'onMyMethodToCall1' ); 
                    this.addActionButton( 'button_2_id', _('Button 2 label'), 'onMyMethodToCall2' ); 
                    this.addActionButton( 'button_3_id', _('Button 3 label'), 'onMyMethodToCall3' ); 
                    break;
*/
                }
            }
        },        

        ///////////////////////////////////////////////////
        //// Utility methods

        getCardUniqueId : function(color, value) {
            return (color - 1) * 10 + (value - 2);
        },

        playCardOnTable : function(player_id, color, value, card_id) {
            // player_id => direction
            dojo.place(this.format_block('jstpl_cardontable', {
                x : this.cardwidth * (value - 2),
                y : this.cardheight * (color - 1),
                player_id : player_id
            }), 'playertablecard_' + player_id);

            if (player_id != this.player_id) {
                // Some opponent played a card
                // Move card from player panel
                this.placeOnObject('cardontable_' + player_id, 'overall_player_board_' + player_id);
            } else {
                // You played a card. If it exists in your hand, move card from there and remove
                // corresponding item
                if ($('myhand_item_' + card_id)) {
                    this.placeOnObject('cardontable_' + player_id, 'myhand_item_' + card_id);
                    this.playerHand.removeFromStockById(card_id);
                }
            }

            // In any case: move it to its final destination
            this.slideToObject('cardontable_' + player_id, 'playertablecard_' + player_id).play();
        },

        buildDeckOnTable: function(gamedatas) {
            // Create as many deck cards as there are cards to be drawn
            for (var k = 1; k <= gamedatas.cardsindeck; k++) {
                dojo.place(this.format_block('jstpl_mydeck', {
                    deckid: k
                }), 'mydeck_wrap');
            }

            // Add deck's remaining cards label
            if (gamedatas.cardsindeck) {
                dojo.place(this.format_block('jstpl_remaining_cards', {
                    remainingcards: gamedatas.cardsindeck
                }), 'remainingcards_wrap');
            }

            // Finally, add briscola if present
            var briscola = gamedatas.briscola;
            if (briscola) {
                var color = briscola.type;
                var value = briscola.type_arg;
                this.briscolaCard.addToStockWithId(this.getCardUniqueId(color, value), briscola.id);
            }
        },

        ///////////////////////////////////////////////////
        //// Player's action

        onPlayerHandSelectionChanged : function() {
            var items = this.playerHand.getSelectedItems();

            if (items.length > 0) {
                var action = 'playCard';
                if (this.checkAction(action, true)) {
                    // Can play a card
                    var card_id = items[0].id;
                    this.ajaxcall("/" + this.game_name + "/" + this.game_name + "/" + action + ".html", {
                        id : card_id,
                        lock : true
                    }, this, function(result) {
                    }, function(is_error) {
                    });

                    this.playerHand.unselectAll();
                } else if (this.checkAction('giveCards')) {
                    // Can give cards => let the player select some cards
                } else {
                    this.playerHand.unselectAll();
                }
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
            dojo.subscribe('newHand', this, "notif_newHand");
            dojo.subscribe('playCard', this, "notif_playCard");

            dojo.subscribe( 'trickWin', this, "notif_trickWin" );
            this.notifqueue.setSynchronous( 'trickWin', 1000 );
            dojo.subscribe( 'giveAllCardsToPlayer', this, "notif_giveAllCardsToPlayer" );
            dojo.subscribe( 'drawNewCard', this, "notif_drawNewCard" );

            dojo.subscribe( 'newScores', this, "notif_newScores" );
        },  

        notif_newHand : function(notif) {
            // We received a new full hand of 13 cards.
            this.playerHand.removeAll();

            for ( var i in notif.args.cards) {
                var card = notif.args.cards[i];
                var color = card.type;
                var value = card.type_arg;
                this.playerHand.addToStockWithId(this.getCardUniqueId(color, value), card.id);
            }

            // Show deck on table
            this.buildDeckOnTable(this.gamedatas);
        },

        notif_playCard : function(notif) {
            // Play a card on the table
            this.playCardOnTable(notif.args.player_id, notif.args.color, notif.args.value, notif.args.card_id);
        },

        notif_trickWin : function(notif) {
            // We do nothing here, just wait in order players can view the cards played before they're gone.
        },

        notif_giveAllCardsToPlayer : function(notif) {
            // Move all cards on table to given table, then destroy them
            var winner_id = notif.args.player_id;
            for ( var player_id in this.gamedatas.players) {
                var anim = this.slideToObject('cardontable_' + player_id, 'overall_player_board_' + winner_id);
                dojo.connect(anim, 'onEnd', function(node) {
                    dojo.destroy(node);
                });
                anim.play();
            }
        },

        notif_drawNewCard: function(notif) {
            var self = this;

            // Variabili riguardanti animazione della pescata
            var deck_index_to_pick = notif.args.deck_index_to_pick;
            var deck_index_to_start_delete_from = notif.args.deck_index_to_start_delete_from;
            var decks_to_delete = notif.args.decks_to_delete;
            var remaining_cards_deck_label = notif.args.remaining_cards_deck_label;

            // Variabili riguardanti la carta pescata
            var card = notif.args.card;
            var color = card.type;
            var value = card.type_arg;

            // Se deck_index_to_pick e' 0, pescare la briscola!
            if (deck_index_to_pick == 0) {
                var anim = this.slideToObject('briscola_wrap', 'myhand');
            } else {
                var anim = this.slideToObject('mydeck_' + deck_index_to_pick, 'myhand');
            }

            dojo.connect(anim, 'onEnd', function(node) {
                dojo.destroy(node);

                // Togli carte dal deck
                for (var i = deck_index_to_start_delete_from; i > deck_index_to_start_delete_from - decks_to_delete; i--) {
                    if (i == 0) {
                        // Destroy the briscola and exit
                        dojo.destroy('briscola_wrap');
                        break;
                    }

                    dojo.destroy('mydeck_' + i);
                }

                // Aggiorna carte rimanenti
                dojo.destroy('remainingcards');
                if (remaining_cards_deck_label > 0) {
                    dojo.place(self.format_block('jstpl_remaining_cards', {
                        remainingcards: remaining_cards_deck_label
                    }), 'remainingcards_wrap');
                }

                self.playerHand.addToStockWithId(self.getCardUniqueId(color, value), card.id);
            });
            anim.play();
        },

        notif_newScores : function(notif) {
            // Update players' scores
            for ( var player_id in notif.args.newScores) {
                this.scoreCtrl[player_id].toValue(notif.args.newScores[player_id]);
            }
        },
   });             
});
