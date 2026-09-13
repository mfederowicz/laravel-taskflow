<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ForgotPasswordChallengeRequest;
use App\Http\Requests\ForgotPasswordResetRequest;
use App\Models\User;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

#[Group('Authentication')]
class ForgotPasswordController extends Controller
{
    /**
     * Return the security question for an account.
     *
     * Public preamble to the reset flow: reveals the stored question only for
     * active accounts that have one set. Unknown emails, locked accounts and
     * accounts without a question all reply with `{ question: null }`.
     */
    public function challenge(ForgotPasswordChallengeRequest $request): JsonResponse
    {
        $user = User::where('email', $request->validated('email'))->first();

        $question = ($user?->isActive() && $user->security_question)
            ? $user->security_question
            : null;

        return response()->json([
            'data' => [
                'question' => $question,
            ],
        ]);
    }

    /**
     * Reset the password using the security answer.
     *
     * Public endpoint. A correct answer resets the password and revokes the
     * account's Sanctum and Passport tokens (JWT tokens are stateless and
     * cannot be revoked). All failure modes — unknown email, locked account,
     * no question set, or a wrong answer — return the same generic answer
     * error so callers cannot distinguish them.
     */
    public function reset(ForgotPasswordResetRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $user = User::where('email', $validated['email'])->first();

        if (
            ! $user
            || ! $user->isActive()
            || ! $user->security_answer
            || ! Hash::check($validated['answer'], $user->security_answer)
        ) {
            throw ValidationException::withMessages([
                'answer' => ['The security answer is incorrect.'],
            ]);
        }

        $user->update(['password' => $validated['password']]);

        $user->tokens()->delete();

        DB::table('oauth_access_tokens')
            ->where('user_id', $user->id)
            ->update(['revoked' => true]);

        DB::table('oauth_refresh_tokens')
            ->whereIn('access_token_id', function ($query) use ($user) {
                $query->select('id')
                    ->from('oauth_access_tokens')
                    ->where('user_id', $user->id);
            })
            ->update(['revoked' => true]);

        return response()->json([
            'data' => [
                'ok' => true,
            ],
        ]);
    }
}
