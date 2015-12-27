<?php

namespace Wallabag\CoreBundle\Tests\Helper;

use Wallabag\CoreBundle\Entity\Config;
use Wallabag\CoreBundle\Entity\Entry;
use Wallabag\CoreBundle\Entity\Tag;
use Wallabag\CoreBundle\Entity\TaggingRule;
use Wallabag\UserBundle\Entity\User;
use Wallabag\CoreBundle\Helper\RuleBasedTagger;

class RuleBasedTaggerTest extends \PHPUnit_Framework_TestCase
{
    private $rulerz;
    private $tagRepository;
    private $entryRepository;
    private $tagger;

    public function setUp()
    {
        $this->rulerz = $this->getRulerZMock();
        $this->tagRepository = $this->getTagRepositoryMock();
        $this->entryRepository = $this->getEntryRepositoryMock();

        $this->tagger = new RuleBasedTagger($this->rulerz, $this->tagRepository, $this->entryRepository);
    }

    public function testTagWithNoRule()
    {
        $entry = new Entry($this->getUser());

        $this->tagger->tag($entry);

        $this->assertTrue($entry->getTags()->isEmpty());
    }

    public function testTagWithNoMatchingRule()
    {
        $taggingRule = $this->getTaggingRule('rule as string', array('foo', 'bar'));
        $user = $this->getUser([$taggingRule]);
        $entry = new Entry($user);

        $this->rulerz
            ->expects($this->once())
            ->method('satisfies')
            ->with($entry, 'rule as string')
            ->willReturn(false);

        $this->tagger->tag($entry);

        $this->assertTrue($entry->getTags()->isEmpty());
    }

    public function testTagWithAMatchingRule()
    {
        $taggingRule = $this->getTaggingRule('rule as string', array('foo', 'bar'));
        $user = $this->getUser([$taggingRule]);
        $entry = new Entry($user);

        $this->rulerz
            ->expects($this->once())
            ->method('satisfies')
            ->with($entry, 'rule as string')
            ->willReturn(true);

        $this->tagger->tag($entry);

        $this->assertFalse($entry->getTags()->isEmpty());

        $tags = $entry->getTags();
        $this->assertSame('foo', $tags[0]->getLabel());
        $this->assertSame($user, $tags[0]->getUser());
        $this->assertSame('bar', $tags[1]->getLabel());
        $this->assertSame($user, $tags[1]->getUser());
    }

    public function testTagWithAMixOfMatchingRules()
    {
        $taggingRule = $this->getTaggingRule('bla bla', array('hey'));
        $otherTaggingRule = $this->getTaggingRule('rule as string', array('foo'));

        $user = $this->getUser([$taggingRule, $otherTaggingRule]);
        $entry = new Entry($user);

        $this->rulerz
            ->method('satisfies')
            ->will($this->onConsecutiveCalls(false, true));

        $this->tagger->tag($entry);

        $this->assertFalse($entry->getTags()->isEmpty());

        $tags = $entry->getTags();
        $this->assertSame('foo', $tags[0]->getLabel());
        $this->assertSame($user, $tags[0]->getUser());
    }

    public function testWhenTheTagExists()
    {
        $taggingRule = $this->getTaggingRule('rule as string', array('foo'));
        $user = $this->getUser([$taggingRule]);
        $entry = new Entry($user);
        $tag = new Tag($user);

        $this->rulerz
            ->expects($this->once())
            ->method('satisfies')
            ->with($entry, 'rule as string')
            ->willReturn(true);

        $this->tagRepository
            ->expects($this->once())
            ->method('findOneByLabelAndUserId')
            ->willReturn($tag);

        $this->tagger->tag($entry);

        $this->assertFalse($entry->getTags()->isEmpty());

        $tags = $entry->getTags();
        $this->assertSame($tag, $tags[0]);
    }

    public function testSameTagWithDifferentfMatchingRules()
    {
        $taggingRule = $this->getTaggingRule('bla bla', array('hey'));
        $otherTaggingRule = $this->getTaggingRule('rule as string', array('hey'));

        $user = $this->getUser([$taggingRule, $otherTaggingRule]);
        $entry = new Entry($user);

        $this->rulerz
            ->method('satisfies')
            ->willReturn(true);

        $this->tagger->tag($entry);

        $this->assertFalse($entry->getTags()->isEmpty());

        $tags = $entry->getTags();
        $this->assertCount(1, $tags);
    }

    private function getUser(array $taggingRules = [])
    {
        $user = new User();
        $config = new Config($user);

        $user->setConfig($config);

        foreach ($taggingRules as $rule) {
            $config->addTaggingRule($rule);
        }

        return $user;
    }

    private function getTaggingRule($rule, array $tags)
    {
        $taggingRule = new TaggingRule();
        $taggingRule->setRule($rule);
        $taggingRule->setTags($tags);

        return $taggingRule;
    }

    private function getRulerZMock()
    {
        return $this->getMockBuilder('RulerZ\RulerZ')
            ->disableOriginalConstructor()
            ->getMock();
    }

    private function getTagRepositoryMock()
    {
        return $this->getMockBuilder('Wallabag\CoreBundle\Repository\TagRepository')
            ->disableOriginalConstructor()
            ->getMock();
    }

    private function getEntryRepositoryMock()
    {
        return $this->getMockBuilder('Wallabag\CoreBundle\Repository\EntryRepository')
            ->disableOriginalConstructor()
            ->getMock();
    }
}
