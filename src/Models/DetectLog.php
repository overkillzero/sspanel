<?php

declare(strict_types=1);

namespace App\Models;

use App\Utils\Tools;

final class DetectLog extends Model
{
    protected $connection = 'default';
    protected $table = 'detect_log';

    /**
     * 用户
     */
    public function user(): ?User
    {
        return User::find($this->user_id);
    }

    /**
     * 用户名
     */
    public function userName(): string
    {
        return $this->user() === null ? '用户不存在' : $this->user()->user_name;
    }

    /**
     * 节点
     */
    public function node(): ?Node
    {
        return Node::find($this->node_id);
    }

    /**
     * 节点名
     */
    public function nodeName(): string
    {
        return $this->node() === null ? '节点不存在' : $this->node()->name;
    }

    /**
     * 规则
     */
    public function rule(): ?DetectRule
    {
        return DetectRule::find($this->list_id);
    }

    /**
     * 规则名
     */
    public function ruleName(): string
    {
        return $this->rule() === null ? '规则不存在' : $this->rule()->name;
    }

    /**
     * 规则描述
     */
    public function ruleText(): string
    {
        return $this->rule() === null ? '规则不存在' : $this->rule()->text;
    }

    /**
     * 规则正则表达式
     */
    public function ruleRegex(): string
    {
        return $this->rule() === null ? '规则已不存在' : $this->rule()->regex;
    }

    /**
     * 规则类型
     */
    public function ruleType(): string
    {
        return $this->rule() === null ? '规则已不存在' : $this->rule()->type();
    }

    /**
     * 时间
     */
    public function datetime(): string
    {
        return Tools::toDateTime($this->datetime);
    }
}
