<?php

namespace blog\BlogBundle\Form;

use blog\BlogBundle\Entity\ArticleCompetence;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

class ArticleCompetenceType extends AbstractType
{
  public function buildForm(FormBuilderInterface $builder, array $options)
  {
    $builder
      ->add('competence', 'entity', array(
        'class'   => 'SdzBlogBundle:Competence'
      ))
      ->add('niveau', 'choice', array(
        'choices' => ArticleCompetence::getNiveaux()
      ))
    ;
  }

  public function setDefaultOptions(OptionsResolverInterface $resolver)
  {
    $resolver->setDefaults(array(
      'data_class' => 'blog\BlogBundle\Entity\ArticleCompetence'
    ));
  }

  public function getName()
  {
    return 'sdz_blogbundle_articlecompetencetype';
  }
}
