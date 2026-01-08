<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $users = \App\Models\User::all();

        foreach ($users as $user) {
            // Create personal team
            $team = \App\Models\Team::create([
                'user_id' => $user->id,
                'name' => $user->name . "'s Team",
                'personal_team' => true,
            ]);

            // Attach user to team
            $user->teams()->attach($team, ['role' => 'admin']);

            // Update resources that have user_id column
            if (Schema::hasColumn('clients', 'user_id')) {
                \App\Models\Client::where('user_id', $user->id)->update(['team_id' => $team->id]);
            }
            if (Schema::hasColumn('audio_records', 'user_id')) {
                \App\Models\AudioRecord::where('user_id', $user->id)->update(['team_id' => $team->id]);
            }

            // For tables without user_id, assign all to the first team if not already assigned
            if ($user->id === 1) { // Only for the first user
                if (Schema::hasColumn('document_templates', 'team_id')) {
                    \App\Models\DocumentTemplate::whereNull('team_id')->update(['team_id' => $team->id]);
                }
                if (Schema::hasColumn('questionnaire_risques', 'team_id')) {
                    \App\Models\QuestionnaireRisque::whereNull('team_id')->update(['team_id' => $team->id]);
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Optional: Revert changes (complex because data might have been mixed)
    }
};
