{OVERALL_GAME_HEADER}

<!-- 
--------
-- BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
-- Briscola implementation : © Tommaso Pifferi <p.tommy93@gmail.com> & Antonio <ai6chr+briscola@gmail.com>
-- 
-- This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
-- See http://en.boardgamearena.com/#!doc/Studio for more information.
-------
-->

<div id="wholetable">
    <div id="othercards">
        <div id="mydeck_wrap" class="whiteblock">
        </div>

        <div id="remainingcards_wrap">
        </div>

        <div id="briscola_wrap">
        </div>
    </div>

    <div id="playertables">
        <!-- BEGIN player -->
        <div class="playertable whiteblock playertable_{DIR}">
            <div class="playertablename" style="color:#{PLAYER_COLOR}">
                {PLAYER_NAME}
            </div>
            <div class="playertablecard" id="playertablecard_{PLAYER_ID}">
            </div>
        </div>
        <!-- END player -->

        <div id="dealer_icon"></div>
        <div id="orientation" class="counterclockwise-icon"></div>
    </div>

    <div id="change_deck_style_wrapper" class="whiteblock">
        <div>
            <span>{NOW_USING_DECK}</span>
            <strong id='current_style'></strong>
        </div>
        <a class='bgabutton bgabutton_gray change-deck-style'>
            <span>{CHANGE_DECK_STYLE}</span>
        </a>
    </div>

    <div id="myhand_wrap" class="whiteblock">
        <h3>{MY_HAND}</h3>
        <div id="myhand">
        </div>
    </div>

</div>

<script type="text/javascript">

// Javascript HTML templates

var jstpl_cardontable = '<div class="cardontable ${deck_type}" id="cardontable_${player_id}" style="background-position:-${x}px -${y}px" data-value="${value}" data-color="${color}"></div>';
var jstpl_mydeck = '<div class="mydeck" id="mydeck"></div>';
var jstpl_remaining_cards = '<div id="remainingcards"><h4>${remainingcards}</h4></div>';
var jstpl_player_board = '<div class="cp_board"><div id="tricks_p${id}" class="tricks_icon"></div> &#x00D7 <span id="trickscount_p${id}">0</span></div>';

</script>  

{OVERALL_GAME_FOOTER}
