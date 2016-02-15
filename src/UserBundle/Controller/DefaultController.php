<?php

namespace UserBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;

class DefaultController extends Controller
{
    public function indexAction()
    {
        return $this->render('UserBundle:Default:index.html.twig');
    }

    /**
     * @param $user1id
     * @param $user2id
     * @return Response
     * @throws NotFoundHttpException
     */
    public function addFriendAction($user1id, $user2id)
    {
        $em = $this->getDoctrine()->getManager();
        $userRepository = $em->getRepository('UserBundle:User');

        $user1 = $userRepository->find($user1id);
        $user2 = $userRepository->find($user2id);

        if (null === $user1 || null === $user2 ) {
            throw new NotFoundHttpException('Friends not found');
        }

        $user1->addFriend($user2);
        return new Response("Friend add");
    }

    /**
     * @param $username
     * @return Response
     */
    public function searchFriendAction($username)
    {
        $em = $this->getDoctrine()->getManager();
        $userRepository = $em->getRepository('UserBundle:User');

        $user = $userRepository->searchFriend($username);

        return new Response("Search");
    }
}
