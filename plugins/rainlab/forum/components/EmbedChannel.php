<?php namespace RainLab\Forum\Components;

use Cms\Classes\Page;
use Cms\Classes\ComponentBase;
use RainLab\Forum\Models\Topic as TopicModel;
use RainLab\Forum\Models\Channel as ChannelModel;
use Exception;

class EmbedChannel extends ComponentBase
{

    public $embedMode = true;

    public function componentDetails()
    {
        return [
            'name'        => 'Embed Channel',
            'description' => 'Attach a channel to any page.'
        ];
    }

    public function defineProperties()
    {
        return [
            'idParam' => [
                'title'             => 'Embed code param',
                'description'       => 'A unique code for the generated channel. A routing parameter can also be used.',
                'type'              => 'string',
            ],
            'topicParam' => [
                'title'             => 'Topic code param',
                'description'       => 'The URL route parameter used for looking up a topic by its slug.',
                'type'              => 'string',
                'default'           => ':topicSlug',
            ],
            'channelId' => [
                'title'             => 'Parent Channel',
                'description'       => 'Specify the channel to create the new channel in',
                'type'              => 'dropdown'
            ],
            'memberPage' => [
                'title'             => 'Member Page',
                'description'       => 'Page name to use for clicking on a member.',
                'type'              => 'dropdown',
            ],
        ];
    }

    protected function getChannelIdOptions()
    {
        return ChannelModel::listsNested('title', 'slug', ' - ');
    }

    public function getMemberPageOptions()
    {
        return Page::sortBy('baseFileName')->lists('baseFileName', 'baseFileName');
    }

    public function onInit()
    {
        $code = $this->propertyOrParam('idParam');

        if (!$code)
            throw new Exception('No code specified for the Forum Embed component');

        $parentChannel = ($channelId = $this->property('channelId'))
            ? ChannelModel::whereSlug($channelId)->first()
            : null;

        if (!$parentChannel)
            throw new Exception('No channel specified for Forum Embed component');

        $properties = $this->getProperties();

        /*
         * Proxy as topic
         */
        if (post('channel') || $this->propertyOrParam('topicParam')) {
            $properties['idParam'] = $this->property('topicParam');
            $component = $this->addComponent('RainLab\Forum\Components\Topic', $this->alias, $properties);
        }
        /*
         * Proxy as channel
         */
        else {
            if ($channel = ChannelModel::forEmbed($parentChannel, $code)->first())
                $properties['idParam'] = $channel->slug;

            $properties['topicPage'] = $this->page->baseFileName;
            $properties['topicPageIdParam'] = $this->property('topicParam');
            $component = $this->addComponent('RainLab\Forum\Components\Channel', $this->alias, $properties);

            /*
             * If a channel does not already exist, generate it when the page ends.
             * This can be disabled by the page setting embedMode to FALSE, for example,
             * if the page returns 404 a channel should not be generated.
             */
            if (!$channel) {
                $this->controller->bindEvent('page.end', function() use ($component, $parentChannel, $code) {
                    if ($component->embedMode !== false) {
                        $channel = ChannelModel::createForEmbed($code, $parentChannel, $this->page->title);
                        $component->setProperty('idParam', $channel->slug);
                        $component->onRun();
                    }
                });
            }
        }

        /*
         * Set the embedding mode
         */
        if (post('channel'))
            $component->embedMode = 'post';
        elseif (post('search'))
            $component->embedMode = 'search';
        elseif ($this->propertyOrParam('topicParam'))
            $component->embedMode = 'topic';
        else
            $component->embedMode = 'channel';
    }

}