<?php

declare(strict_types=1);

namespace App\Controllers\User;

use App\Controllers\BaseController;
use App\Models\Setting;
use App\Models\Ticket;
use App\Services\Notification;
use App\Services\RateLimit;
use App\Utils\Tools;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Message\ResponseInterface;
use RedisException;
use Slim\Http\Response;
use Slim\Http\ServerRequest;
use Telegram\Bot\Exceptions\TelegramSDKException;
use voku\helper\AntiXSS;
use function array_merge;
use function count;
use function json_decode;
use function json_encode;
use function time;

/**
 *  TicketController
 */
final class TicketController extends BaseController
{
    /**
     * @throws Exception
     */
    public function ticket(ServerRequest $request, Response $response, array $args): ?ResponseInterface
    {
        if (! Setting::obtain('enable_ticket')) {
            return $response->withRedirect('/user');
        }

        $tickets = Ticket::where('userid', $this->user->id)->orderBy('datetime', 'desc')->get();

        foreach ($tickets as $ticket) {
            $ticket->status = $ticket->status();
            $ticket->type = $ticket->type();
            $ticket->datetime = Tools::toDateTime((int) $ticket->datetime);
        }

        return $response->write(
            $this->view()
                ->assign('tickets', $tickets)
                ->fetch('user/ticket/index.tpl')
        );
    }

    /**
     * @throws RedisException
     * @throws ClientExceptionInterface
     * @throws TelegramSDKException
     * @throws GuzzleException
     */
    public function ticketAdd(ServerRequest $request, Response $response, array $args): ResponseInterface
    {
        $title = $request->getParam('title') ?? '';
        $comment = $request->getParam('comment') ?? '';
        $type = $request->getParam('type') ?? '';

        if (! Setting::obtain('enable_ticket') ||
            $this->user->is_shadow_banned ||
            ! RateLimit::checkTicketLimit($this->user->id)
        ) {
            return $response->withJson([
                'ret' => 0,
                'msg' => '暂时无法开启新工单，请稍后再试',
            ]);
        }

        if ($title === '' || $comment === '' || $type === '') {
            return $response->withJson([
                'ret' => 0,
                'msg' => '工单内容不能为空',
            ]);
        }

        $antiXss = new AntiXSS();

        $content = [
            [
                'comment_id' => 0,
                'commenter_name' => $this->user->user_name,
                'comment' => $antiXss->xss_clean($comment),
                'datetime' => time(),
            ],
        ];

        $ticket = new Ticket();
        $ticket->title = $antiXss->xss_clean($title);
        $ticket->content = json_encode($content);
        $ticket->userid = $this->user->id;
        $ticket->datetime = time();
        $ticket->status = 'open_wait_admin';
        $ticket->type = $antiXss->xss_clean($type);
        $ticket->save();

        if (Setting::obtain('mail_ticket')) {
            Notification::notifyAdmin(
                $_ENV['appName'] . '-新工单被开启',
                '管理员，有人开启了新的工单，请你及时处理。'
            );
        }

        return $response->withJson([
            'ret' => 1,
            'msg' => '提交成功',
        ]);
    }

    /**
     * @throws GuzzleException
     * @throws TelegramSDKException
     * @throws ClientExceptionInterface
     */
    public function ticketUpdate(ServerRequest $request, Response $response, array $args): ResponseInterface
    {
        if (! Setting::obtain('enable_ticket')) {
            return $response->withJson([
                'ret' => 0,
                'msg' => '暂时无法回复工单，请稍后再试',
            ]);
        }

        $id = $args['id'];
        $comment = $request->getParam('comment') ?? '';

        if ($this->user->is_shadow_banned) {
            return $response->withJson([
                'ret' => 0,
                'msg' => '暂时无法回复工单，请稍后再试',
            ]);
        }

        if ($comment === '') {
            return $response->withJson([
                'ret' => 0,
                'msg' => '工单回复不能为空',
            ]);
        }

        $ticket = Ticket::where('id', $id)->where('userid', $this->user->id)->first();

        if ($ticket === null) {
            return $response->withJson([
                'ret' => 0,
                'msg' => '工单不存在',
            ]);
        }

        $antiXss = new AntiXSS();

        $content_old = json_decode($ticket->content, true);
        $content_new = [
            [
                'comment_id' => $content_old[count($content_old) - 1]['comment_id'] + 1,
                'commenter_name' => $this->user->user_name,
                'comment' => $antiXss->xss_clean($comment),
                'datetime' => time(),
            ],
        ];

        $ticket->content = json_encode(array_merge($content_old, $content_new));
        $ticket->status = 'open_wait_admin';
        $ticket->save();

        if (Setting::obtain('mail_ticket')) {
            Notification::notifyAdmin(
                $_ENV['appName'] . '-工单被回复',
                '管理员，有人回复了 <a href="' .
                $_ENV['baseUrl'] . '/admin/ticket/' . $ticket->id . '/view">#' . $ticket->id .
                '</a> 工单，请你及时处理。'
            );
        }

        return $response->withJson([
            'ret' => 1,
            'msg' => '提交成功',
        ]);
    }

    /**
     * @throws Exception
     */
    public function ticketView(ServerRequest $request, Response $response, array $args): ResponseInterface
    {
        if (! Setting::obtain('enable_ticket')) {
            return $response->withRedirect('/user');
        }

        $id = $args['id'];
        $ticket = Ticket::where('id', '=', $id)->where('userid', $this->user->id)->first();

        if ($ticket === null) {
            return $response->withRedirect('/user/ticket');
        }

        $comments = json_decode($ticket->content);

        foreach ($comments as $comment) {
            $comment->datetime = Tools::toDateTime((int) $comment->datetime);
        }

        $ticket->status = $ticket->status();
        $ticket->type = $ticket->type();
        $ticket->datetime = Tools::toDateTime((int) $ticket->datetime);

        return $response->write(
            $this->view()
                ->assign('ticket', $ticket)
                ->assign('comments', $comments)
                ->fetch('user/ticket/view.tpl')
        );
    }
}
