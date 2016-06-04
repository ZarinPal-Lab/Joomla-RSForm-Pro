DROP TABLE IF EXISTS `#__rsform_payment`;

DELETE FROM #__rsform_config WHERE SettingName = 'zarinpal.merchantid';
DELETE FROM #__rsform_config WHERE SettingName = 'zarinpal.gatetype';
DELETE FROM #__rsform_config WHERE SettingName = 'zarinpal.test';

DELETE FROM #__rsform_component_types WHERE ComponentTypeId = 642;
DELETE FROM #__rsform_component_type_fields WHERE ComponentTypeId = 642;
