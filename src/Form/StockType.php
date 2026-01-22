<?php

namespace App\Form;

use App\Entity\Stock;
use App\Entity\Product;
use App\Repository\ProductRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class StockType extends AbstractType
{
    public function __construct(
        private ProductRepository $productRepository
    ) {}
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('products', EntityType::class, [
                'label' => 'Products',
                'class' => Product::class,
                'choice_label' => function (Product $product) {
                    return sprintf('%s (ID: %d)', $product->getName(), $product->getId());
                },
                'multiple' => true,
                'expanded' => false,
                'required' => false,
                'placeholder' => $this->hasProducts() ? 'Select products (optional)' : 'No product created',
                'query_builder' => function (ProductRepository $er) {
                    return $er->createQueryBuilder('p')
                        ->orderBy('p.name', 'ASC');
                },
                'empty_data' => [],
                'attr' => [
                    'class' => 'form-input'
                ]
            ])
            ->add('Quantity', IntegerType::class, [
                'label' => 'Current Quantity',
                'attr' => [
                    'class' => 'form-input',
                    'placeholder' => 'Enter current stock quantity'
                ]
            ])
            ->add('stockType', ChoiceType::class, [
                'label' => 'Stock Type',
                'choices' => [
                    'Seedlings' => 'seedlings',
                    'Marcotted' => 'marcotted',
                    'Grafted' => 'grafted'
                ],
                'placeholder' => 'Select stock type',
                'required' => false,
                'attr' => [
                    'class' => 'form-input'
                ]
            ])
            ->add('minimumQuantity', IntegerType::class, [
                'label' => 'Minimum Quantity',
                'required' => false,
                'attr' => [
                    'class' => 'form-input',
                    'placeholder' => 'Enter minimum stock level'
                ]
            ])
            ->add('maximumQuantity', IntegerType::class, [
                'label' => 'Maximum Quantity',
                'required' => false,
                'attr' => [
                    'class' => 'form-input',
                    'placeholder' => 'Enter maximum stock level'
                ]
            ])
            ->add('location', TextType::class, [
                'label' => 'Storage Location',
                'required' => false,
                'attr' => [
                    'class' => 'form-input',
                    'placeholder' => 'Enter storage location (e.g., Warehouse A, Shelf 3)'
                ]
            ])
        ;
    }

    private function hasProducts(): bool
    {
        return $this->productRepository->count([]) > 0;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Stock::class,
        ]);
    }
}
