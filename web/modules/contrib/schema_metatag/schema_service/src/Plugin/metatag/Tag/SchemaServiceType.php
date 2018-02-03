<?php

namespace Drupal\schema_service\Plugin\metatag\Tag;

use Drupal\schema_metatag\Plugin\metatag\Tag\SchemaTypeBase;

/**
 * Provides a plugin for the 'type' meta tag.
 *
 * - 'id' should be a globally unique id.
 * - 'name' should match the Schema.org element name.
 * - 'group' should match the id of the group that defines the Schema.org type.
 *
 * @MetatagTag(
 *   id = "schema_service_type",
 *   label = @Translation("@type"),
 *   description = @Translation("The type of service (fixed by standard)."),
 *   name = "@type",
 *   group = "schema_service",
 *   weight = -5,
 *   type = "string",
 *   secure = FALSE,
 *   multiple = FALSE
 * )
 */
class SchemaServiceType extends SchemaTypeBase {

  /**
   * {@inheritdoc}
   */
  public static function labels() {
    return [
      'Service',
      'BroadcastService',
      'CableOrSatelliteService',
      'FinancialProduct',
      '- BankAccount',
      '-- DepositAccount',
      '- CurrencyConversionService',
      '- InvestmentOrDeposit',
      '-- DepositAccount',
      '- LoanOrCredit',
      '-- CreditCard',
      '- PaymentCard',
      '- PaymentService',
      'FoodService',
      'GovernmentService',
      'TaxiService',
    ];
  }

}
