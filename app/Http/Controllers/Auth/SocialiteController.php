<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Configuration;
use App\Models\DiscordUser;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Laravel\Socialite\Facades\Socialite;

class SocialiteController extends Controller
{
    public function redirect()
    {
        $scopes = !empty(env('DISCORD_BOT_TOKEN')) && !empty(env('DISCORD_GUILD_ID')) ? ['guilds.join'] : [];

        return Socialite::driver('discord')
            ->scopes($scopes)
            ->redirect();
    }

    public function callback()
    {
        if (Auth::guest()) {
            return abort(500);
        }

        $discord = Socialite::driver('discord')->user();
        $discordUser = DiscordUser::find($discord->id);

        $guildId = env('DISCORD_GUILD_ID');
        $botToken = env('DISCORD_BOT_TOKEN');

        //force user into discord server
        //TODO Add event on failure, to notify ppl involved
        if (!empty($guildId) && !empty($botToken)) {
            $response = Http::withHeaders(
                [
                    'Authorization' => 'Bot ' . $botToken,
                    'Content-Type' => 'application/json',
                ]
            )->put("https://discord.com/api/guilds/{$guildId}/members/{$discord->id}",
                ['access_token' => $discord->token]);
        }


        if (is_null($discordUser)) {
            //create discord user in db
            DiscordUser::create(array_merge($discord->user, ['user_id' => Auth::user()->id]));
            //update user
            Auth::user()->increment('credits', Configuration::getValueByKey('CREDITS_REWARD_AFTER_VERIFY_DISCORD'));
            Auth::user()->increment('server_limit', Configuration::getValueByKey('SERVER_LIMIT_REWARD_AFTER_VERIFY_DISCORD'));
            Auth::user()->update(['discord_verified_at' => now()]);
        } else {
            $discordUser->update($discord->user);
        }

        return redirect()->route('profile.index')->with(
            'success',
            'Discord account linked!'
        );
    }
}
