<?php
 /**
  *------
  * BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
  * BriscolaSuperamici implementation : © <Your name here> <Your email address here>
  * 
  * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
  * See http://en.boardgamearena.com/#!doc/Studio for more information.
  * -----
  * 
  * briscolasuperamici.game.php
  *
  * This is the main file for your game logic.
  *
  * In this PHP file, you are going to defines the rules of the game.
  *
  */


require_once( APP_GAMEMODULE_PATH.'module/table/table.game.php' );


class BriscolaSuperamici extends Table
{
	function __construct( )
	{
        // Your global variables labels:
        //  Here, you can assign labels to global variables you are using for this game.
        //  You can use any number of global variables with IDs between 10 and 99.
        //  If your game has options (variants), you also have to associate here a label to
        //  the corresponding ID in gameoptions.inc.php.
        // Note: afterwards, you can get/set the global variables with getGameStateValue/setGameStateInitialValue/setGameStateValue
        parent::__construct();

        self::initGameStateLabels(array(
            "primoSemeGiocato" => 10,
            "semeBriscola" => 11,
            "valoreBriscola" => 12,
            "idCartaBriscola" => 13,
            "numeroTurno" => 14
        ));

        // Init $this->cards to be a deck
        $this->cards = self::getNew( "module.common.deck" );
        $this->cards->init( "card" );

        // Assign a big negative value to briscola card, so that it's drawn as last card
        $this->briscola_location_arg = -1000;

        // Various points related stuff
        $this->draw_score = 60;
        $this->minimum_winning_score = 61;
        $this->winning_hands_to_end_game = 3;
        $this->number_of_cards_in_full_deck = 40;
	}
	
    protected function getGameName( )
    {
		// Used for translations and stuff. Please do not modify.
        return "briscolasuperamici";
    }	

    /*
        setupNewGame:
        
        This method is called only once, when a new game is launched.
        In this method, you must setup the game according to the game rules, so that
        the game is ready to be played.
    */
    protected function setupNewGame( $players, $options = array() )
    {    
        // Set the colors of the players with HTML color code
        // The default below is red/green/blue/orange/brown
        // The number of colors defined here must correspond to the maximum number of players allowed for the gams
        $gameinfos = self::getGameinfos();
        $default_colors = $gameinfos['player_colors'];
 
        // Create players
        // Note: if you added some extra field on "player" table in the database (dbmodel.sql), you can initialize it there.
        $sql = "INSERT INTO player (player_id, player_color, player_canal, player_name, player_avatar) VALUES ";
        $values = array();
        foreach( $players as $player_id => $player )
        {
            $color = array_shift( $default_colors );
            $values[] = "('".$player_id."','$color','".$player['player_canal']."','".addslashes( $player['player_name'] )."','".addslashes( $player['player_avatar'] )."')";
        }
        $sql .= implode( $values, ',' );
        self::DbQuery( $sql );
        self::reattributeColorsBasedOnPreferences( $players, $gameinfos['player_colors'] );
        self::reloadPlayersBasicInfos();
        
        /************ Start the game initialization *****/

        self::setGameStateInitialValue( 'primoSemeGiocato', 0 );
        self::setGameStateInitialValue( 'semeBriscola', 0 );
        self::setGameStateInitialValue( 'valoreBriscola', 0 );
        self::setGameStateInitialValue( 'idCartaBriscola', 0 );
        self::setGameStateInitialValue( 'numeroTurno', 0 );

        // Create cards
        $cards = array ();
        foreach ( $this->colors as $color_id => $color ) {
            // spade, heart, diamond, club
            for ($value = 2; $value <= 11; $value ++) {
                //  2, 4, 5 ... K, 3, A
                $cards [] = array ('type' => $color_id,'type_arg' => $value,'nbr' => 1);
            }
        }

        // Create cards deck
        $this->cards->createCards( $cards, 'deck');

        // Activate first player (which is in general a good idea :) )
        $this->activeNextPlayer();

        /************ End of the game initialization *****/
    }

    /*
        getAllDatas: 
        
        Gather all informations about current game situation (visible by the current player).
        
        The method is called each time the game interface is displayed to a player, ie:
        _ when the game starts
        _ when a player refreshes the game page (F5)
    */
    protected function getAllDatas()
    {
        $result = array();

        // !! We must only return informations visible by this player !!
        $current_player_id = self::getCurrentPlayerId();
    
        // Get information about players
        // Note: you can retrieve some extra field you added for "player" table in "dbmodel.sql" if you need it.
        $sql = "SELECT player_id id, player_score score FROM player ";
        $result['players'] = self::getCollectionFromDb( $sql );

        // Gather all information about current game situation (visible by player $current_player_id).

        // Cards in player hand
        $result['hand'] = $this->cards->getCardsInLocation( 'hand', $current_player_id );

        // Cards played on the table
        $result['cardsontable'] = $this->cards->getCardsInLocation( 'cardsontable' );

        $cards_in_deck = $this->cards->getCardsInLocation('deck');
        if (count($cards_in_deck) > 0) {
            $result['cardsindeck'] = count($cards_in_deck) - 1;

            $idCartaBriscola = self::getGameStateValue( 'idCartaBriscola' );
            foreach ( $cards_in_deck as $card_id => $card ) {
                if ($card_id == $idCartaBriscola) {
                    $result['briscola'] = $card;
                    break;
                }
            }

        } else {
            $result['cardsindeck'] = 0;
            $result['briscola'] = null;
        }


        // TODO: Far vedere seme della briscola
  
        return $result;
    }

    /*
        getGameProgression:
        
        Compute and return the current game progression.
        The number returned must be an integer beween 0 (=the game just started) and
        100 (= the game is finished or almost finished).
    
        This method is called each time we are in a game state with the "updateGameProgression" property set to true 
        (see states.inc.php)
    */
    function getGameProgression()
    {
        $current_cards_in_deck = $this->cards->countCardsInLocation('deck');
        $drawn_card_ratio = ($this->number_of_cards_in_full_deck - $current_cards_in_deck) / (float) $this->number_of_cards_in_full_deck;
        self::error("Drawn card ratio is " . $drawn_card_ratio . " FINE!");

        $userScores = self::getCollectionFromDb("SELECT player_id, player_score FROM player", true );
        $maxScore = 0;
        foreach( $userScores as $player_id => $score ) {
            if ($score > $maxScore) {
                $maxScore = $score;
            }
        }
        self::error("Max score is " . $maxScore . " FINE!");

        $base = 1 / (float) $this->winning_hands_to_end_game * ($maxScore) * 100;
        $current = $drawn_card_ratio * 100 / (float) $this->winning_hands_to_end_game;
        $progress = $base + $current;

        self::error("Base is: " . $base . " FINE!");
        self::error("Current is: " . $current . " FINE!");
        self::error("Game progress: " . $progress . " FINE!");

        return $progress;
    }


//////////////////////////////////////////////////////////////////////////////
//////////// Utility functions
////////////    

    /*
        In this space, you can put any utility methods useful for your game logic
    */

    function getPlayersToDirection()
    {
        $result = array();

        $players = self::loadPlayersBasicInfos();
        $nextPlayer = self::createNextPlayerTable( array_keys( $players ) );

        $current_player = self::getCurrentPlayerId();

        if (count($players) == 2) {
            $directions = array( 'S', 'N' );
        } else if (count($players) == 4) {
            $directions = array( 'S', 'W', 'N', 'E' );
        }


        if( ! isset( $nextPlayer[ $current_player ] ) )
        {
            // Spectator mode: take any player for south
            $player_id = $nextPlayer[0];
            $result[ $player_id ] = array_shift( $directions );
        }
        else
        {
            // Normal mode: current player is on south
            $player_id = $current_player;
            $result[ $player_id ] = array_shift( $directions );
        }

        while( count( $directions ) > 0 )
        {
            $player_id = $nextPlayer[ $player_id ];
            $result[ $player_id ] = array_shift( $directions );
        }
        return $result;
    }


//////////////////////////////////////////////////////////////////////////////
//////////// Player actions
//////////// 

    /*
        Each time a player is doing some game action, one of the methods below is called.
        (note: each method below must match an input method in briscolasuperamici.action.php)
    */

    function playCard($card_id) {
        self::checkAction("playCard");
        $player_id = self::getActivePlayerId();
        $this->cards->moveCard($card_id, 'cardsontable', $player_id);

        $currentCard = $this->cards->getCard($card_id);
        $currentTrickColor = self::getGameStateValue( 'primoSemeGiocato' ) ;
        if( $currentTrickColor == 0 ) {
            self::setGameStateValue( 'primoSemeGiocato', $currentCard['type'] );
        }

        // And notify
        self::notifyAllPlayers('playCard', clienttranslate('${player_name} plays ${value_displayed} ${color_displayed}'),
            array (
            'i18n' => array ('color_displayed','value_displayed' ),
            'card_id' => $card_id,
            'player_id' => $player_id,
            'player_name' => self::getActivePlayerName(),
            'value' => $currentCard ['type_arg'],
            'value_displayed' => $this->values_label [$currentCard ['type_arg']],
            'color' => $currentCard ['type'],
            'color_displayed' => $this->colors [$currentCard ['type']] ['name']
            ));

        $this->gamestate->nextState('playCard');
    }
    
//////////////////////////////////////////////////////////////////////////////
//////////// Game state arguments
////////////

    /*
        Here, you can create methods defined as "game state arguments" (see "args" property in states.inc.php).
        These methods function is to return some additional information that is specific to the current
        game state.
    */

    function argGiveCards() {
        return array ();
    }

//////////////////////////////////////////////////////////////////////////////
//////////// Game state actions
////////////

    /*
        Here, you can create methods defined as "game state actions" (see "action" property in states.inc.php).
        The action method of state X is called everytime the current game state is set to X.
    */

    function stNewHand() {
        // Stats
        self::incStat(1, "handNbr");
        self::setGameStateValue('numeroTurno', self::getGameStateValue('numeroTurno') + 1);

        // Take back all cards (from any location => null) to deck
        $this->cards->moveAllCardsInLocation(null, "deck");
        $this->cards->shuffle('deck');

        $total_cards = $this->cards->countCardsInLocation('deck');

        // Choose briscola
        // N.B: The briscola index goes from 1 to len(array) because cards are indexed using auto increment IDs
        $briscola_index = rand(1, $total_cards);
        $briscola = $this->cards->getCardsInLocation('deck')[$briscola_index];
        $idBriscola = $briscola['id'];

        self::setGameStateValue('semeBriscola', $briscola['type'] );
        self::setGameStateValue('valoreBriscola', $briscola['type_arg'] );
        self::setGameStateValue('idCartaBriscola', $idBriscola );

        // Set big negative location_arg to briscola, so that it's drawn at last for sure
        $sql = "UPDATE card SET card_location_arg=$this->briscola_location_arg WHERE card_id='$idBriscola'";
        self::DbQuery($sql);

        // Deal 3 cards to each players and give some other info to the client
        $players = self::loadPlayersBasicInfos();

        // Subtract: 3 cards per player and the briscola
        $cardsindeck = $total_cards - (count($players) * 3) - 1;

        foreach ( $players as $player_id => $player ) {
            $cards = $this->cards->pickCards(3, 'deck', $player_id);

            // Notify player about his cards, as well as other infos that are useful
            // when passing from one hand to another, without refreshing
            // (thus, without calling getAllData function)
            self::notifyPlayer($player_id, 'newHand', '', array (
                'cards' => $cards,
                'cardsindeck' => $cardsindeck,
                'briscola' => $briscola ));
        }

        $this->gamestate->nextState("");
    }

    function stNewTrick() {
        // Active the player who wins the last trick
        // Reset trick color to 0 (= no color)
        self::setGameStateInitialValue('primoSemeGiocato', 0);
        $this->gamestate->nextState("");
    }

    function stDrawCards() {
        // Pesca le carte dal mazzo e le da' a ciascun giocatore.
        // Anziche' implementare un giro effettivo di pescate (come la briscola offline),
        // facciamo pescare a tutti la prima carta nel deck, tranne quando l'ultimo deve pescare la briscola
        $players = self::loadPlayersBasicInfos();
        $number_of_players = count($players);
        $last_winner_id = self::getActivePlayerId();

        $cards_in_deck_count = $this->cards->countCardInLocation('deck');
        $last_winner_position = $players[$last_winner_id]['player_no'];
        for ($i = 0; $i < $number_of_players; $i++) {
            $player_id_give_card_to = null;
            foreach ( $players as $player_id => $player ) {
                $current_player_position = $players[$player_id]['player_no'];

                $next_player_position = $last_winner_position + $i;
                if ($next_player_position > $number_of_players) {
                    $next_player_position = $next_player_position % $number_of_players;
                }

                if ($current_player_position == $next_player_position) {
                    $player_id_give_card_to = $player_id;
                    break;
                }
            }

            $card = $this->cards->pickCard('deck', $player_id_give_card_to);
            $remaining_cards = $cards_in_deck_count - $i - 1;
            $remaining_cards_deck_label = max($cards_in_deck_count - $number_of_players - 1, 0);
            if ($remaining_cards > 0) {
                self::notifyPlayer($player_id_give_card_to, 'drawNewCard', '', array (
                    'card' => $card,
                    'deck_index_to_pick'=> $cards_in_deck_count - 1,
                    'deck_index_to_start_delete_from' => $cards_in_deck_count - 1,
                    'decks_to_delete' => $number_of_players,
                    'remaining_cards_deck_label' => $remaining_cards_deck_label,
                    'delete_briscola_from_deck' => $remaining_cards_deck_label == 0,
                    'id_briscola' => self::getGameStateValue('idCartaBriscola')));
            } else {
                self::notifyPlayer($player_id_give_card_to, 'drawNewCard', '', array (
                    'card' => $card,
                    'deck_index_to_pick'=> 0,
                    'deck_index_to_start_delete_from' => $cards_in_deck_count - 1,
                    'decks_to_delete' => $number_of_players,
                    'remaining_cards_deck_label' => 0,
                    'delete_briscola_from_deck'=> false,
                    'id_briscola' => self::getGameStateValue('idCartaBriscola')));
            }

        }

        $this->gamestate->nextState("");
    }

    function stNextPlayer() {
        // Active next player OR end the trick and go to the next trick OR end the hand
        // TODO: Vedere quanti giocatori stanno giocando e selezionare il numero giusto
        if ($this->cards->countCardInLocation('cardsontable') == 2) {
            // This is the end of the trick
            $cards_on_table = $this->cards->getCardsInLocation('cardsontable');
            $best_value = 0;
            $best_value_player_id = null;
            $currentTrickColor = self::getGameStateValue('primoSemeGiocato');
            $semeBriscola = self::getGameStateValue('semeBriscola');

            $semeCorrente = $currentTrickColor;
            $primaBriscolaTrovata = false;
            foreach ( $cards_on_table as $card ) {
                if ($card ['type'] == $semeBriscola) {
                    if (!$primaBriscolaTrovata) {
                        $semeCorrente = $semeBriscola;
                        // Note: location_arg = player who played this card on table
                        $best_value_player_id = $card ['location_arg'];
                        // Note: type_arg = value of the card
                        $best_value = $card ['type_arg'];
                        $primaBriscolaTrovata = true;
                        continue;
                    }
                }
                if ($card ['type'] == $semeCorrente) {
                    if ($best_value_player_id === null || $card ['type_arg'] > $best_value) {
                        // Note: location_arg = player who played this card on table
                        $best_value_player_id = $card ['location_arg'];
                        // Note: type_arg = value of the card
                        $best_value = $card ['type_arg'];
                    }
                }
            }

            // Active this player => he's the one who starts the next trick
            $this->gamestate->changeActivePlayer( $best_value_player_id );

            // Move all cards to "cardswon" of the given player
            $this->cards->moveAllCardsInLocation('cardsontable', 'cardswon', null, $best_value_player_id);

            // Notify
            // Note: we use 2 notifications here in order we can pause the display during the first notification
            // before we move all cards to the winner (during the second)
            $players = self::loadPlayersBasicInfos();
            self::notifyAllPlayers( 'trickWin', clienttranslate('${player_name} wins the trick'), array(
                'player_id' => $best_value_player_id,
                'player_name' => $players[ $best_value_player_id ]['player_name']
            ) );
            self::notifyAllPlayers( 'giveAllCardsToPlayer','', array(
                'player_id' => $best_value_player_id
            ) );


            // TODO: If there are 4 players, and there are 0 cards in the deck,
            // we need to show each other' cards to companions.

            if ($this->cards->countCardInLocation('hand') == 0) {
                // End of the hand
                $this->gamestate->nextState("endHand");
            } else {
                // End of the trick
                if ($this->cards->countCardInLocation('deck') > 0) {
                    $this->gamestate->nextState("nextCards");
                } else {
                    $this->gamestate->nextState("nextTrick");
                }
            }
        } else {
            // Standard case (not the end of the trick)
            // => just active the next player
            $player_id = self::activeNextPlayer();
            self::giveExtraTime($player_id);
            $this->gamestate->nextState('nextPlayer');
        }
    }

    function stEndHand() {
        // Count and score points, then end the game or go to the next hand.
        $players = self::loadPlayersBasicInfos();

        $player_to_points = array ();
        foreach ( $players as $player_id => $player ) {
            $player_to_points [$player_id] = 0;
        }
        
        
        $cards = $this->cards->getCardsInLocation("cardswon");
        foreach ( $cards as $card ) {
            $player_id = $card ['location_arg'];

            /**
             * Summary of briscola's rules
             *
             * Cards nominal values:    2  4  5  6  7  J  Q  K  3  A
             * Cards internal values:   2  3  4  5  6  7  8  9  10 11
             * Cards points:            0  0  0  0  0  2  3  4  10 11
             */
            if ($card ['type_arg'] == 7) {
            	/* J */
            	$player_to_points [$player_id] += 2; 
            } else if ($card ['type_arg'] == 8) {
            	/* Q */
            	$player_to_points [$player_id] += 3; 
            } else if ($card ['type_arg'] == 9) {
            	/* K */
            	$player_to_points [$player_id] += 4; 
            } else if ($card ['type_arg'] == 10) {
            	/* Tre */
            	$player_to_points [$player_id] += 10; 
            } else if ($card ['type_arg'] == 11) {
            	/* Asso */
        		$player_to_points [$player_id] += 11; 
            }
        }

        // TODO: Add team logic when dealing with 4 players
        
        // Notify score to players
        foreach ( $player_to_points as $player_id => $points ) {
            self::notifyAllPlayers("points", clienttranslate('${player_name} makes ${points} points'), array (
                'player_id' => $player_id,
                'player_name' => $players [$player_id] ['player_name'],
                'points' => $points ));
        }

        // Apply score to players and notify hand winner or draw
        foreach ( $player_to_points as $player_id => $points ) {
            if ($points >= $this->minimum_winning_score) {
                $sql = "UPDATE player SET player_score=player_score+1  WHERE player_id='$player_id'";
                self::DbQuery($sql);

                self::notifyAllPlayers("points", clienttranslate('${player_name} wins the hand'), array (
                    'player_id' => $player_id,
                    'player_name' => $players [$player_id] ['player_name']));
            } else if ($points == $this->draw_score) {
                self::notifyAllPlayers("points", clienttranslate('Draw!'));
            }
        }

        $newScores = self::getCollectionFromDb("SELECT player_id, player_score FROM player", true );
        self::notifyAllPlayers( "newScores", '', array( 'newScores' => $newScores ) );

        // Display table window with results

        $table = array();

        // Header line
        $firstRow = array( '' );
        foreach( $players as $player_id => $player ) {
            $firstRow[] = array(
                'str' => '${player_name}',
                'args' => array( 'player_name' => $player['player_name'] ),
                'type' => 'header'
            );
        }
        $table[] = $firstRow;

        // Points
        $newRow = array( array( 'str' => clienttranslate('Current game points'), 'args' => array() ) );
        foreach( $player_to_points as $player_id => $points ) {
            if ($points >= $this->minimum_winning_score) {
                $newRow[] = clienttranslate("" . $points . " (<b>Win</b>)");
            } else if ($points == $this->draw_score) {
                $newRow[] = clienttranslate("" . $points . " (<b>Draw</b>)");
            } else {
                $newRow[] = clienttranslate("" . $points . " (<b>Lose</b>)");
            }
        }
        $table[] = $newRow;

        // Final score
        $newRow = array( array( 'str' => clienttranslate('Overall score'), 'args' => array() ) );
        foreach( $newScores as $player_id => $score ) {
            $newRow[] = "<b>" . $score . "</b>";
        }
        $table[] = $newRow;

        $this->notifyAllPlayers( "tableWindow", '', array(
            "id" => 'finalScoring',
            "title" => clienttranslate("Result of this hand"),
            "table" => $table,
            "closing" => "Close"
        ) );

        // End game
        foreach( $newScores as $player_id => $score ) {
            if( $score >= $this->winning_hands_to_end_game ) {
                // Trigger the end of the game !
                $this->gamestate->nextState("endGame");
                return;
            }
        }

        // Otherwise... new hand !
        $this->gamestate->nextState("nextHand");
    }

//////////////////////////////////////////////////////////////////////////////
//////////// Zombie
////////////

    /*
        zombieTurn:
        
        This method is called each time it is the turn of a player who has quit the game (= "zombie" player).
        You can do whatever you want in order to make sure the turn of this player ends appropriately
        (ex: pass).
    */

    function zombieTurn( $state, $active_player )
    {
    	$statename = $state['name'];
    	
        if ($state['type'] === "activeplayer") {
            switch ($statename) {
                default:
                    $this->gamestate->nextState( "zombiePass" );
                	break;
            }

            return;
        }

        if ($state['type'] === "multipleactiveplayer") {
            // Make sure player is in a non blocking status for role turn
            $this->gamestate->setPlayerNonMultiactive( $active_player, '' );
            
            return;
        }

        throw new feException( "Zombie mode not supported at this game state: ".$statename );
    }
    
///////////////////////////////////////////////////////////////////////////////////:
////////// DB upgrade
//////////

    /*
        upgradeTableDb:
        
        You don't have to care about this until your game has been published on BGA.
        Once your game is on BGA, this method is called everytime the system detects a game running with your old
        Database scheme.
        In this case, if you change your Database scheme, you just have to apply the needed changes in order to
        update the game database and allow the game to continue to run with your new version.
    
    */
    
    function upgradeTableDb( $from_version )
    {
        // $from_version is the current version of this game database, in numerical form.
        // For example, if the game was running with a release of your game named "140430-1345",
        // $from_version is equal to 1404301345
        
        // Example:
//        if( $from_version <= 1404301345 )
//        {
//            $sql = "ALTER TABLE xxxxxxx ....";
//            self::DbQuery( $sql );
//        }
//        if( $from_version <= 1405061421 )
//        {
//            $sql = "CREATE TABLE xxxxxxx ....";
//            self::DbQuery( $sql );
//        }
//        // Please add your future database scheme changes here
//
//


    }    
}
