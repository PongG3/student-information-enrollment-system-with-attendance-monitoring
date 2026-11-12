<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pages opened inside a dialog ("?embed=1", see resources/js/page-dialog.js).
 *
 * A form sent from a dialog carries "_embed". When the save fails, Laravel redirects back to the
 * dialog's own page (which already has embed=1), so the errors show inside the dialog.
 * When it succeeds, the redirect goes to an ordinary page; instead of loading that page inside
 * the dialog (which would use up its one-time messages, such as a new temporary password),
 * a tiny page tells the window behind the dialog to go there. The same goes for a dialog page
 * that sends the user elsewhere on opening (e.g. editing a class of a closed term).
 */
class DialogEmbed
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $fromDialog = $request->isMethod('GET') ? $request->boolean('embed') : $request->boolean('_embed');

        if ($fromDialog && $response instanceof RedirectResponse) {
            $target = $response->getTargetUrl();
            parse_str((string) parse_url($target, PHP_URL_QUERY), $query);

            if (empty($query['embed'])) {
                return response()->view('dialog-done', ['url' => $target]);
            }
        }

        return $response;
    }
}
