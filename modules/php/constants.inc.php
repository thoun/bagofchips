<?php

/*
 * Chip types
 */
define('A', 'A');
define('B', 'B');

/*
 * Color
 */
define('EQUAL', -1);
const DIFFERENT = 0;
const RED = 1;
const YELLOW = 2;
const GREEN = 3;
const BLUE = 4;
const PURPLE = 5;

/*
 * Gain
 */
const VP = 1;
const BRACELET = 2;
const RECRUIT = 3;
const REPUTATION = 4;
const CARD = 5;

/*
 * Artifacts
 */
const ARTIFACT_MEAD_CUP = 1;
const ARTIFACT_SILVER_COIN = 2;
const ARTIFACT_CAULDRON = 3;
const ARTIFACT_GOLDEN_BRACELET = 4;
const ARTIFACT_HELMET = 5;
const ARTIFACT_AMULET = 6;
const ARTIFACT_WEATHERVANE = 7;

/*
 * State constants
 */
const ST_BGA_GAME_SETUP = 1;

const ST_START_ROUND = 10;

const ST_REVEAL_CHIPS = 20;

const ST_MULTIPLAYER_DISCARD_CARDS = 30;

const ST_MULTIPLAYER_PLACE_CARDS = 40;

const ST_END_ROUND = 80;

const ST_END_SCORE = 90;

const ST_END_GAME = 99;
const END_SCORE = 100;

/*
 * Constants
 */
const LAST_TURN = 10;
const RECRUIT_DONE = 11;
const EXPLORE_DONE = 12;
const TRADE_DONE = 15;
const GO_DISCARD_TABLE_CARD = 16;
const GO_RESERVE = 17;
const PLAYED_CARD_COLOR = 20;
const SELECTED_CHIP = 21;
const COMPLETED_LINES = 22;

/*
 * Global variables
 */
const PHASE = 'PHASE';

?>
