<?php

function sbb_get_default_bots() {
        return array(
                'googlebot',
                'googlebot-image',
                'googlebot-news',
                'mediapartners-google',
                'adsbot-google',
                'bingbot',
                'duckduckbot',
                'baiduspider',
                'yandexbot',
                'yandeximages',
                'yandexmobilebot',
                'slurp',
                'exabot',
                'sogou spider',
                'facebot',
                'twitterbot',
                'applebot',
                'petalbot',
        );
}

function sbb_get_blocked_bots() {
        $bots    = sbb_get_default_bots();
        $options = staging_bot_block_get_options();

        if ( ! empty( $options['extra_user_agents'] ) ) {
                $extra = preg_split( '/\r\n|\r|\n/', $options['extra_user_agents'] );

                if ( is_array( $extra ) ) {
                        $extra = array_filter( array_map( 'trim', $extra ) );

                        if ( ! empty( $extra ) ) {
                                $bots = array_merge( $bots, $extra );
                        }
                }
        }

        return $bots;
}

function sbb_is_blocked_bot( $ua ) {
        if ( empty( $ua ) ) {
                return false;
        }

        $ua      = strtolower( $ua );
        $blocked = sbb_get_blocked_bots();

        foreach ( $blocked as $bot ) {
                $bot = strtolower( trim( $bot ) );

                if ( '' === $bot ) {
                        continue;
                }

                if ( false !== strpos( $ua, $bot ) ) {
                        return true;
                }
        }

        return false;
}
