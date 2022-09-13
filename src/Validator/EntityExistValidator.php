<?php

namespace App\Validator;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class EntityExistValidator extends ConstraintValidator
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function validate($value, Constraint $constraint)
    {
        if (!$constraint instanceof EntityExist) {
            throw new UnexpectedTypeException($constraint, EntityExist::class);
        }

        if (null === $value) {
            return null;
        }

        if ($constraint->field) {
            $query = $this->entityManager
                ->createQueryBuilder()
                ->select('e')
                ->from($constraint->entityClass, 'e')
                ->where('e.'.$constraint->field.' = :value')
                ->setParameter('value', $value);

            if ($constraint->ignoreValues) {
                $i = 0;
                foreach ($constraint->ignoreValues as $ignoreField => $ignoreValue) {
                    $query->andWhere('e.'.$ignoreField.' != :ignoreValue_'.$i)
                        ->setParameter('ignoreValue_'.$i, $constraint->ignoreValues);
                    ++$i;
                }
            }

            $entity = $query->getQuery()->getResult();

            if ($entity) {
                $this->context->buildViolation($constraint->message)
                    ->setParameter('{{ string }}', $value)
                    ->addViolation();
            }
        } else {
            if (!$this->entityManager->find($constraint->entityClass, $value)) {
                $this->context->buildViolation($constraint->message)
                    ->setParameter('{{ string }}', $value)
                    ->addViolation();
            }
        }
    }
}
