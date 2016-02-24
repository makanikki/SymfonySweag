<?php
namespace MessengerBundle\Controller;

use FOS\RestBundle\Controller\Annotations\Get;
use FOS\RestBundle\Controller\Annotations\Post;
use FOS\RestBundle\Controller\Annotations\RequestParam;
use FOS\RestBundle\Request\ParamFetcher;
use MessengerBundle\Entity\Conversation;
use MessengerBundle\Utils\Traits\GetManagersTrait;
use MessengerBundle\Utils\Traits\PopulateValueObjectsTrait;
use Nelmio\ApiDocBundle\Annotation\ApiDoc;
use FOS\RestBundle\Controller\Annotations\RouteResource;
use FOS\RestBundle\Controller\FOSRestController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use UserBundle\Controller\LoginApiController;

/**
 * Class ConversationsController
 * @package MessengerBundle\Controller
 *
 * @RouteResource("Conversation")
 */
class ConversationsController extends FOSRestController
{
    use GetManagersTrait;
    use PopulateValueObjectsTrait;

    // Get

    /**
     * @ApiDoc(
     *  resource = true,
     *  description = "Returns all conversations of an user",
     *  statusCodes = {
     *      200 = "Returned when sucessful",
     *      404 = "Returned when no messages are found"
     *  }
     * )
     *
     * @Get("/get/{username}")
     *
     * @throws NotFoundHttpException
     */
    public function cgetAction($username)
    {
        $userRepository = $this->getUserRepository();

        $user = $userRepository->findOneByUsernameWithConversations($username);

        if (null === $user) {
            throw new NotFoundHttpException('The user provided does not exist');
        }

        /** @var Conversation[] $conversationsFromBase */
        $conversationsFromBase = $user->getConversations();

        if (empty($conversationsFromBase)) {
            throw new NotFoundHttpException('No message found');
        }

        $conversations = [];
        foreach($conversationsFromBase as $conversation) {
            $conversationValueObject = $this->populateConversationValueObject($conversation);

            $conversations[] = $conversationValueObject;
        }

        $view = $this->view($conversations);

        return $this->handleView($view);
    }

    /**
     * @ApiDoc(
     *  resource = true,
     *  description = "Returns all messages of a conversation",
     *  statusCodes = {
     *      200 = "Returned when sucessful",
     *      404 = "Returned when no messages are found"
     *  }
     * )
     *
     * @Get("/get/messages/{id}")
     *
     * @throws NotFoundHttpException
     */
    public function getMessagesAction($id)
    {
        $conversationRepository = $this->getConversationRepository();
        /** @var Conversation $conversation */
        $conversation = $conversationRepository->findWithMessages($id);

        if (null === $conversation) {
            throw new NotFoundHttpException('No conversation found');
        }

        $messagesFromBase = $conversation->getMessages();

        $messages = [];
        foreach($messagesFromBase as $message) {
            $messages[] = $this->populateMessageValueObject($message);
        }

        return $this->view($messages);
    }

    // Post

    /**
     * @param ParamFetcher $paramFetcher
     * @param Request $request
     *
     * @return View
     * @throws \HttpInvalidParamException
     *
     * @Post("/new")
     *
     * @RequestParam(name="to", nullable=true, description="Message receiver")
     */
    public function postAction(ParamFetcher $paramFetcher, Request $request)
    {
        $em = $this->getManager();
        $user1 = LoginApiController::checkAuthentication($request, $em);

        $to = $paramFetcher->get('to');

        if (null === $to) {
            throw new \HttpInvalidParamException('You must specify a second participant');
        }

        $userRepository = $this->getUserRepository();

        $user2 = $userRepository->find($to);

        if ($this->conversationWithBothParticipantsAlreadyExists($user1, $user2)) {
            return $this->view('A conversation with those users already exists');
        }

        $conversationHandler = $this->get('messenger.conversation_handler');
        $conversation = $conversationHandler->createConversation($user1, $user2);

        $em->persist($conversation);

        $em->flush();

        return $this->view($this->populateConversationValueObject($conversation));
    }

    /**
     * @param $user1
     * @param $user2
     * @return bool
     */
    private function conversationWithBothParticipantsAlreadyExists($user1, $user2)
    {
        $conversationRepository = $this->getConversationRepository();

        $user1Conversations = $conversationRepository->findByParticipants($user1->getId());
        $user2Conversations = $conversationRepository->findByParticipants($user2->getId());

        foreach($user1Conversations as $conversation) {
            if (in_array($conversation, $user2Conversations)) {
                return true;
            }
        }

        return false;
    }
}
