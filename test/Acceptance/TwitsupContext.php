<?php
declare(strict_types=1);

namespace Test\Acceptance;

use Assert\Assertion;
use Behat\Behat\Context\Context;
use Behat\Behat\Context\SnippetAcceptingContext;
use Behat\Behat\Tester\Exception\PendingException;
use Behat\Gherkin\Node\PyStringNode;
use Behat\Gherkin\Node\TableNode;
use Common\EventSourcing\EventStore\StorageFacility;
use Ramsey\Uuid\Uuid;
use Twitsup\Application\FollowUser;
use Twitsup\Application\FollowUserHandler;
use Twitsup\Application\RegisterUser;
use Twitsup\Application\RegisterUserHandler;
use Twitsup\Application\SendTweet;
use Twitsup\Application\SendTweetHandler;
use Twitsup\ReadModel\TimelineRepository;
use Twitsup\ReadModel\UserLookupRepository;

class TwitsupContext implements Context, SnippetAcceptingContext
{
    private $container;

    /**
     * @var Uuid
     */
    private $userId;

    /**
     * @var string
     */
    private $username;

    public function __construct()
    {
        $this->container = require __DIR__ . '/../../app/container.php';
    }

    /**
     * @BeforeScenario
     */
    public function cleanUp()
    {
        foreach ($this->container['read_model_repositories'] as $repository) {
            $repository->reset();
        }
        $this->container[StorageFacility::class]->deleteAll();
    }

    /**
     * @Given I've registered myself as :username (:nickname)
     */
    public function iVeRegisteredAs(string $username, string $nickname)
    {
        $this->userId = Uuid::uuid4();
        $this->username = $username;

        $registerUserHandler = $this->container->get(RegisterUserHandler::class);
        $registerUser = new RegisterUser();
        $registerUser->id = (string)$this->userId;
        $registerUser->username = $username;
        $registerUser->nickname = $nickname;

        $registerUserHandler->__invoke($registerUser);
    }

    /**
     * @Given a user :username (:nickname) has also registered themselves
     */
    public function aUserHasAlsoRegisteredThemselves(string $username, string $nickname)
    {
        $registerUserHandler = $this->container->get(RegisterUserHandler::class);
        $registerUser = new RegisterUser();
        $registerUser->id = (string)Uuid::uuid4();
        $registerUser->username = $username;
        $registerUser->nickname = $nickname;

        $registerUserHandler->__invoke($registerUser);
    }

    /**
     * @When I follow :followeeUsername
     */
    public function iFollow(string $followeeUsername)
    {
        $followUserHandler = $this->container->get(FollowUserHandler::class);

        $followUser = new FollowUser();
        $followUser->followerUsername = $this->username;
        $followUser->followeeUsername = $followeeUsername;

        $followUserHandler->__invoke($followUser);
    }

    /**
     * @When :username tweets :tweetText
     */
    public function userTweets(string $username, string $tweetText)
    {
        $sendTweet = new SendTweet();

        /** @var UserLookupRepository $userLookupRepository */
        $userLookupRepository = $this->container[UserLookupRepository::class];
        $userId = $userLookupRepository->getUserIdForUsername($username);
        $sendTweet->userId = $userId;
        $sendTweet->text = $tweetText;

        $sendTweetHandler = $this->container[SendTweetHandler::class];
        $sendTweetHandler->__invoke($sendTweet);
    }

    /**
     * @Then I see on my timeline: :tweetText
     */
    public function iSeeOnMyTimeline($tweetText)
    {
        /** @var TimelineRepository $timelineRepository */
        $timelineRepository = $this->container->get(TimelineRepository::class);
        $timeline = $timelineRepository->timelineFor((string)$this->userId);
        Assertion::contains($timeline, $tweetText);
    }
}
