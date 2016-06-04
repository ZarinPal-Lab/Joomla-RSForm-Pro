INSERT IGNORE INTO `#__rsform_config` (`SettingName`, `SettingValue`) VALUES
('zarinpal.merchantid', ''),
('zarinpal.gatetype', '0'),
('zarinpal.test', '0');

INSERT IGNORE INTO `#__rsform_component_types` (`ComponentTypeId`, `ComponentTypeName`) VALUES (642, 'zarinpal');

DELETE FROM #__rsform_component_type_fields WHERE ComponentTypeId = 642;
INSERT IGNORE INTO `#__rsform_component_type_fields` (`ComponentTypeId`, `FieldName`, `FieldType`, `FieldValues`, `Ordering`) VALUES
(642, 'NAME', 'textbox', '', 0),
(642, 'LABEL', 'textbox', '', 1),
(642, 'COMPONENTTYPE', 'hidden', '642', 2),
(642, 'LAYOUTHIDDEN', 'hiddenparam', 'YES', 7);