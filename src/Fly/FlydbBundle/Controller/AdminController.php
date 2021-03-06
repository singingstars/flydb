<?php

namespace Fly\FlydbBundle\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Acl\Domain\ObjectIdentity;
use Symfony\Component\Security\Acl\Domain\UserSecurityIdentity;
use Symfony\Component\Security\Acl\Domain\RoleSecurityIdentity;
use Symfony\Component\Security\Acl\Permission\MaskBuilder;

use Pagerfanta\Pagerfanta;
use Pagerfanta\Adapter\ArrayAdapter;

use Elastica_Searchable;
use Elastica_Query;

use Fly\FlydbBundle\Entity\Flyline;
use Fly\FlydbBundle\Form\FlylineType;

class AdminController extends Controller
{
    public function rebuildUserAclAction($user_id)
    {
        $securityContext = $this->get('security.context');
//        $user = $securityContext->getToken()->getUser();

        if (false === $securityContext->isGranted('ROLE_ADMIN'))
        {
            throw new AccessDeniedException();
        }

        $em = $this->getDoctrine()->getManager();
        $user = $em->getRepository('FlyFlydbBundle:User')->find($user_id);
        $flylines = $em->getRepository('FlyFlydbBundle:Flyline')->findByOwner($user);
        
        foreach ( $flylines as $flyline )
        {
            $flyline->setUpdated(new \DateTime());
            $em->persist($flyline);
            $em->flush();
            
            if ( false === $securityContext->isGranted('MASTER', $flyline) )
            {
                // creating the ACL (deals with when exists)
                $aclProvider = $this->get('security.acl.provider');
                $objectIdentity = ObjectIdentity::fromDomainObject($flyline);
                
                try
                {
                   $acl = $aclProvider->findAcl($objectIdentity);
                }
                catch (\Symfony\Component\Security\Acl\Exception\Exception $e)
                {
                   $acl = $aclProvider->createAcl($objectIdentity);
                }

                // retrieving the security identity of the currently logged-in user
                $securityIdentity = UserSecurityIdentity::fromAccount($user);

                // grant owner access
                $acl->insertObjectAce($securityIdentity, MaskBuilder::MASK_OWNER);
                
                // grant admin access
                $roleSecurityIdentity = new RoleSecurityIdentity('ROLE_ADMIN');
                $acl->insertObjectAce($roleSecurityIdentity, MaskBuilder::MASK_MASTER);
                
                $aclProvider->updateAcl($acl);
            }
        }

        return $this->redirect($this->generateUrl('flymanage'));
    }
}
