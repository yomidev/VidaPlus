-- ============================================================
-- FARMACIA VIDAPLUS — Datos de prueba (seed)
-- Archivo: 02_seed.sql
-- Requiere: 01_schema.sql ya ejecutado
-- ============================================================
-- CONTENIDO:
--   1. Países
--   2. Roles
--   3. Categorías
--   4. Laboratorios
--   5. Proveedores
--   6. Usuarios — IMPORTANTE: ver nota de contraseñas
--   7. Medicamentos
--   8. Clientes
--   9. Direcciones_cliente
--  10. Compras (10) + Detalle_compras (30)
--
-- NOTA SOBRE CONTRASEÑAS DE USUARIOS Y CLIENTES:
--   Los campos password_hash son PLACEHOLDERS. Para activar el login,
--   ejecutar el script PHP scripts/01_generate_passwords.php (ver
--   comentario al final). Contraseñas de prueba:
--     admin         / admin123
--     farmaceutico1 / farma123
--     farmaceutico2 / farma123
--     cajero1       / caja123
--     cajero2       / caja123
--     auditor1      / audit123
-- ============================================================

USE vidaplus;

-- ============================================================
-- 1. PAÍSES
-- ============================================================
INSERT INTO paises (iso2, iso3, nombre, nacionalidad, codigo_tel) VALUES
-- África (54)
('DZ','DZA','Argelia','argelina','+213'),
('AO','AGO','Angola','angoleña','+244'),
('BJ','BEN','Benín','beninesa','+229'),
('BW','BWA','Botsuana','botsuanesa','+267'),
('BF','BFA','Burkina Faso','burkinesa','+226'),
('BI','BDI','Burundi','burundesa','+257'),
('CV','CPV','Cabo Verde','caboverdiana','+238'),
('CM','CMR','Camerún','camerunesa','+237'),
('CF','CAF','República Centroafricana','centroafricana','+236'),
('TD','TCD','Chad','chadiana','+235'),
('KM','COM','Comoras','comorense','+269'),
('CD','COD','República Democrática del Congo','congoleña','+243'),
('CG','COG','República del Congo','congoleña','+242'),
('CI','CIV','Costa de Marfil','marfileña','+225'),
('DJ','DJI','Yibuti','yibutiense','+253'),
('EG','EGY','Egipto','egipcia','+20'),
('GQ','GNQ','Guinea Ecuatorial','ecuatoguineana','+240'),
('ER','ERI','Eritrea','eritrea','+291'),
('SZ','SWZ','Esuatini','suazi','+268'),
('ET','ETH','Etiopía','etíope','+251'),
('GA','GAB','Gabón','gabonesa','+241'),
('GM','GMB','Gambia','gambiana','+220'),
('GH','GHA','Ghana','ghanesa','+233'),
('GN','GIN','Guinea','guineana','+224'),
('GW','GNB','Guinea-Bisáu','bisauguineana','+245'),
('KE','KEN','Kenia','keniana','+254'),
('LS','LSO','Lesoto','lesotense','+266'),
('LR','LBR','Liberia','liberiana','+231'),
('LY','LBY','Libia','libia','+218'),
('MG','MDG','Madagascar','malgache','+261'),
('MW','MWI','Malaui','malauí','+265'),
('ML','MLI','Malí','maliense','+223'),
('MR','MRT','Mauritania','mauritana','+222'),
('MU','MUS','Mauricio','mauriciana','+230'),
('MA','MAR','Marruecos','marroquí','+212'),
('MZ','MOZ','Mozambique','mozambiqueña','+258'),
('NA','NAM','Namibia','namibia','+264'),
('NE','NER','Níger','nigerina','+227'),
('NG','NGA','Nigeria','nigeriana','+234'),
('RW','RWA','Ruanda','ruandesa','+250'),
('ST','STP','Santo Tomé y Príncipe','santotomense','+239'),
('SN','SEN','Senegal','senegalesa','+221'),
('SC','SYC','Seychelles','seychellense','+248'),
('SL','SLE','Sierra Leona','sierraleonesa','+232'),
('SO','SOM','Somalia','somalí','+252'),
('ZA','ZAF','Sudáfrica','sudafricana','+27'),
('SS','SSD','Sudán del Sur','sursudanesa','+211'),
('SD','SDN','Sudán','sudanesa','+249'),
('TZ','TZA','Tanzania','tanzana','+255'),
('TG','TGO','Togo','togolesa','+228'),
('TN','TUN','Túnez','tunecina','+216'),
('UG','UGA','Uganda','ugandesa','+256'),
('ZM','ZMB','Zambia','zambiana','+260'),
('ZW','ZWE','Zimbabue','zimbabuense','+263'),
-- América (35)
('AG','ATG','Antigua y Barbuda','antiguana','+1268'),
('AR','ARG','Argentina','argentina','+54'),
('BS','BHS','Bahamas','bahameña','+1242'),
('BB','BRB','Barbados','barbadense','+1246'),
('BZ','BLZ','Belice','beliceña','+501'),
('BO','BOL','Bolivia','boliviana','+591'),
('BR','BRA','Brasil','brasileña','+55'),
('CA','CAN','Canadá','canadiense','+1'),
('CL','CHL','Chile','chilena','+56'),
('CO','COL','Colombia','colombiana','+57'),
('CR','CRI','Costa Rica','costarricense','+506'),
('CU','CUB','Cuba','cubana','+53'),
('DM','DMA','Dominica','dominiquesa','+1767'),
('DO','DOM','República Dominicana','dominicana','+1809'),
('EC','ECU','Ecuador','ecuatoriana','+593'),
('SV','SLV','El Salvador','salvadoreña','+503'),
('GD','GRD','Granada','granadina','+1473'),
('GT','GTM','Guatemala','guatemalteca','+502'),
('GY','GUY','Guyana','guyanesa','+592'),
('HT','HTI','Haití','haitiana','+509'),
('HN','HND','Honduras','hondureña','+504'),
('JM','JAM','Jamaica','jamaiquina','+1876'),
('MX','MEX','México','mexicana','+52'),
('NI','NIC','Nicaragua','nicaragüense','+505'),
('PA','PAN','Panamá','panameña','+507'),
('PY','PRY','Paraguay','paraguaya','+595'),
('PE','PER','Perú','peruana','+51'),
('KN','KNA','San Cristóbal y Nieves','sancristobaleña','+1869'),
('LC','LCA','Santa Lucía','santalucense','+1758'),
('VC','VCT','San Vicente y las Granadinas','sanvicentina','+1784'),
('SR','SUR','Surinam','surinamesa','+597'),
('TT','TTO','Trinidad y Tobago','trinitense','+1868'),
('US','USA','Estados Unidos','estadounidense','+1'),
('UY','URY','Uruguay','uruguaya','+598'),
('VE','VEN','Venezuela','venezolana','+58'),
-- Asia (48)
('AF','AFG','Afganistán','afgana','+93'),
('AM','ARM','Armenia','armenia','+374'),
('AZ','AZE','Azerbaiyán','azerbaiyana','+994'),
('BH','BHR','Baréin','bareiní','+973'),
('BD','BGD','Bangladés','bangladesí','+880'),
('BT','BTN','Bután','butanesa','+975'),
('BN','BRN','Brunéi','bruneana','+673'),
('KH','KHM','Camboya','camboyana','+855'),
('CN','CHN','China','china','+86'),
('CY','CYP','Chipre','chipriota','+357'),
('GE','GEO','Georgia','georgiana','+995'),
('IN','IND','India','india','+91'),
('ID','IDN','Indonesia','indonesia','+62'),
('IR','IRN','Irán','iraní','+98'),
('IQ','IRQ','Irak','iraquí','+964'),
('IL','ISR','Israel','israelí','+972'),
('JP','JPN','Japón','japonesa','+81'),
('JO','JOR','Jordania','jordana','+962'),
('KZ','KAZ','Kazajistán','kazaja','+7'),
('KW','KWT','Kuwait','kuwaití','+965'),
('KG','KGZ','Kirguistán','kirguís','+996'),
('LA','LAO','Laos','laosiana','+856'),
('LB','LBN','Líbano','libanesa','+961'),
('MY','MYS','Malasia','malasia','+60'),
('MV','MDV','Maldivas','maldiva','+960'),
('MN','MNG','Mongolia','mongola','+976'),
('MM','MMR','Myanmar','birmana','+95'),
('NP','NPL','Nepal','nepalesa','+977'),
('KP','PRK','Corea del Norte','norcoreana','+850'),
('OM','OMN','Omán','omaní','+968'),
('PK','PAK','Pakistán','pakistaní','+92'),
('PS','PSE','Palestina','palestina','+970'),
('PH','PHL','Filipinas','filipina','+63'),
('QA','QAT','Catar','catarí','+974'),
('SA','SAU','Arabia Saudita','saudí','+966'),
('SG','SGP','Singapur','singapurense','+65'),
('KR','KOR','Corea del Sur','surcoreana','+82'),
('LK','LKA','Sri Lanka','esrilanquesa','+94'),
('SY','SYR','Siria','siria','+963'),
('TJ','TJK','Tayikistán','tayika','+992'),
('TH','THA','Tailandia','tailandesa','+66'),
('TL','TLS','Timor Oriental','timorense','+670'),
('TR','TUR','Turquía','turca','+90'),
('TM','TKM','Turkmenistán','turcomana','+993'),
('AE','ARE','Emiratos Árabes Unidos','emiratí','+971'),
('UZ','UZB','Uzbekistán','uzbeka','+998'),
('VN','VNM','Vietnam','vietnamita','+84'),
('YE','YEM','Yemen','yemení','+967'),
-- Europa (44)
('AL','ALB','Albania','albanesa','+355'),
('AD','AND','Andorra','andorrana','+376'),
('AT','AUT','Austria','austriaca','+43'),
('BY','BLR','Bielorrusia','bielorrusa','+375'),
('BE','BEL','Bélgica','belga','+32'),
('BA','BIH','Bosnia y Herzegovina','bosnia','+387'),
('BG','BGR','Bulgaria','búlgara','+359'),
('HR','HRV','Croacia','croata','+385'),
('CZ','CZE','República Checa','checa','+420'),
('DK','DNK','Dinamarca','danesa','+45'),
('EE','EST','Estonia','estonia','+372'),
('FI','FIN','Finlandia','finlandesa','+358'),
('FR','FRA','Francia','francesa','+33'),
('DE','DEU','Alemania','alemana','+49'),
('GR','GRC','Grecia','griega','+30'),
('HU','HUN','Hungría','húngara','+36'),
('IS','ISL','Islandia','islandesa','+354'),
('IE','IRL','Irlanda','irlandesa','+353'),
('IT','ITA','Italia','italiana','+39'),
('LV','LVA','Letonia','letona','+371'),
('LI','LIE','Liechtenstein','liechtensteiniana','+423'),
('LT','LTU','Lituania','lituana','+370'),
('LU','LUX','Luxemburgo','luxemburguesa','+352'),
('MT','MLT','Malta','maltesa','+356'),
('MD','MDA','Moldavia','moldava','+373'),
('MC','MCO','Mónaco','monegasca','+377'),
('ME','MNE','Montenegro','montenegrina','+382'),
('NL','NLD','Países Bajos','neerlandesa','+31'),
('MK','MKD','Macedonia del Norte','macedonia','+389'),
('NO','NOR','Noruega','noruega','+47'),
('PL','POL','Polonia','polaca','+48'),
('PT','PRT','Portugal','portuguesa','+351'),
('RO','ROU','Rumanía','rumana','+40'),
('RU','RUS','Rusia','rusa','+7'),
('SM','SMR','San Marino','sanmarinense','+378'),
('RS','SRB','Serbia','serbia','+381'),
('SK','SVK','Eslovaquia','eslovaca','+421'),
('SI','SVN','Eslovenia','eslovena','+386'),
('ES','ESP','España','española','+34'),
('SE','SWE','Suecia','sueca','+46'),
('CH','CHE','Suiza','suiza','+41'),
('UA','UKR','Ucrania','ucraniana','+380'),
('GB','GBR','Reino Unido','británica','+44'),
('VA','VAT','Ciudad del Vaticano','vaticana','+379'),
-- Oceanía (14)
('AU','AUS','Australia','australiana','+61'),
('FJ','FJI','Fiyi','fiyiana','+679'),
('KI','KIR','Kiribati','kiribatiana','+686'),
('MH','MHL','Islas Marshall','marshalesa','+692'),
('FM','FSM','Micronesia','micronesia','+691'),
('NR','NRU','Nauru','nauruana','+674'),
('NZ','NZL','Nueva Zelanda','neozelandesa','+64'),
('PW','PLW','Palaos','palauana','+680'),
('PG','PNG','Papúa Nueva Guinea','papú','+675'),
('WS','WSM','Samoa','samoana','+685'),
('SB','SLB','Islas Salomón','salomonense','+677'),
('TO','TON','Tonga','tongana','+676'),
('TV','TUV','Tuvalu','tuvaluana','+688'),
('VU','VUT','Vanuatu','vanuatense','+678');

-- ============================================================
-- 2. ROLES (catálogo interno)
-- ============================================================
INSERT INTO roles (nombre, descripcion, nivel_acceso) VALUES
('admin',        'Administrador del sistema con acceso total', 10),
('farmaceutico', 'Farmacéutico con licencia para dispensar',    7),
('cajero',       'Cajero de mostrador',                         4),
('auditor',      'Auditor de solo lectura',                     2);

-- ============================================================
-- 3. CATEGORÍAS
-- ============================================================
INSERT INTO categorias (nombre, descripcion) VALUES
('Analgésicos',        'Medicamentos para el dolor'),
('Antiinflamatorios',  'Reducen la inflamación'),
('Antibióticos',       'Combaten infecciones bacterianas'),
('Antihistamínicos',   'Para alergias'),
('Gastrointestinal',   'Para problemas digestivos'),
('Vitaminas',          'Suplementos nutricionales'),
('Dermatológicos',     'Cuidado de la piel');

-- ============================================================
-- 4. LABORATORIOS (id_pais referencia a paises.id_pais)
-- ============================================================
INSERT INTO laboratorios (nombre, id_pais, telefono, email) VALUES
('FarmaLab México',          (SELECT id_pais FROM paises WHERE iso2='MX'), '5551234567',  'contacto@farmalab.test'),
('BioFarma Colombia',        (SELECT id_pais FROM paises WHERE iso2='CO'), '5712345678',  'info@biofarma.test'),
('MediGen España',           (SELECT id_pais FROM paises WHERE iso2='ES'), '34912345678', 'ventas@medigen.test'),
('NutriLab USA',             (SELECT id_pais FROM paises WHERE iso2='US'), '12125550100', 'sales@nutrilab.test'),
('DermaCare México',         (SELECT id_pais FROM paises WHERE iso2='MX'), '5559876543',  'contacto@dermacare.test'),
('PharmaIndia Ltd',          (SELECT id_pais FROM paises WHERE iso2='IN'), '91112345678', 'exports@pharmaindia.test');

-- ============================================================
-- 5. PROVEEDORES
-- ============================================================
INSERT INTO proveedores (nombre, id_pais, contacto, telefono, email) VALUES
('Distribuidora Central',    (SELECT id_pais FROM paises WHERE iso2='MX'), 'Juan Pérez',    '5551000001', 'juan@distcentral.test'),
('Farmacéutica del Norte',   (SELECT id_pais FROM paises WHERE iso2='MX'), 'María Sánchez', '5551000002', 'maria@farmanorte.test'),
('Global Pharma',            (SELECT id_pais FROM paises WHERE iso2='US'), 'John Smith',    '12125550200','john@globalpharma.test'),
('Insumos del Sur',          (SELECT id_pais FROM paises WHERE iso2='CO'), 'Carlos Ruiz',   '5712345699', 'carlos@insumossur.test'),
('MedSupply Argentina',      (SELECT id_pais FROM paises WHERE iso2='AR'), 'Laura Díaz',    '541112345678','laura@medsupply.test'),
('Health Import',            (SELECT id_pais FROM paises WHERE iso2='DE'), 'Klaus Müller',  '493012345678','klaus@healthimport.test');

-- ============================================================
-- 6. USUARIOS (contraseñas PLACEHOLDER - ver nota al inicio)
-- ============================================================
-- Los hashes son placeholders de 60 caracteres. Se reemplazan con
-- scripts/01_generate_passwords.php (ver comentario al final del archivo).
INSERT INTO usuarios (username, password_hash, nombre_completo, email, id_rol) VALUES
('admin',          '$2y$10$PLACEHOLDER_REPLACE_WITH_REAL_HASH_00000000000000000000000',  'Administrador General',  'admin@vidaplus.test',        1),
('farmaceutico1',  '$2y$10$PLACEHOLDER_REPLACE_WITH_REAL_HASH_00000000000000000000001',  'Ana García',             'ana.garcia@vidaplus.test',   2),
('farmaceutico2',  '$2y$10$PLACEHOLDER_REPLACE_WITH_REAL_HASH_00000000000000000000002',  'Luis López',             'luis.lopez@vidaplus.test',   2),
('cajero1',        '$2y$10$PLACEHOLDER_REPLACE_WITH_REAL_HASH_00000000000000000000003',  'María Martínez',         'maria.martinez@vidaplus.test',4),
('cajero2',        '$2y$10$PLACEHOLDER_REPLACE_WITH_REAL_HASH_00000000000000000000004',  'Carlos Pérez',           'carlos.perez@vidaplus.test', 4),
('auditor1',       '$2y$10$PLACEHOLDER_REPLACE_WITH_REAL_HASH_00000000000000000000005',  'Laura Hernández',        'laura.hernandez@vidaplus.test',2);

-- ============================================================
-- 7. MEDICAMENTOS (50) — requiere_receta controla qué se ve online
-- ============================================================
INSERT INTO medicamentos (codigo_barras, nombre, descripcion, id_categoria, id_laboratorio, presentacion, concentracion, precio_compra, precio_venta, stock, stock_minimo, requiere_receta) VALUES
-- Analgésicos (7)
('7501000000011', 'Paracetamol',        'Analgésico y antipirético',       1, 1, 'Caja 20 tabletas', '500mg',  15.00,  35.00, 250, 30, 0),
('7501000000028', 'Paracetamol',        'Analgésico y antipirético',       1, 1, 'Caja 40 tabletas', '1g',     25.00,  55.00, 180, 30, 0),
('7501000000035', 'Ibuprofeno',         'Analgésico y antiinflamatorio',   1, 1, 'Caja 20 tabletas', '400mg',  20.00,  45.00, 220, 30, 0),
('7501000000042', 'Aspirina',           'Analgésico',                      1, 2, 'Caja 40 tabletas', '500mg',  18.00,  40.00, 150, 25, 0),
('7501000000059', 'Naproxeno',          'Analgésico',                      1, 2, 'Caja 20 tabletas', '250mg',  30.00,  65.00, 120, 25, 0),
('7501000000066', 'Tramadol',           'Analgésico controlado',           1, 3, 'Caja 10 tabletas', '50mg',   45.00, 120.00,  60, 15, 1),
('7501000000073', 'Ketorolaco',         'Analgésico potente',              1, 3, 'Caja 10 tabletas', '30mg',   50.00, 130.00,  45, 15, 1),
-- Antiinflamatorios (6)
('7501000000103', 'Meloxicam',          'Antiinflamatorio',                2, 1, 'Caja 20 tabletas', '15mg',   35.00,  75.00, 100, 20, 1),
('7501000000110', 'Celecoxib',          'Antiinflamatorio selectivo',      2, 2, 'Caja 30 cápsulas', '200mg',  80.00, 170.00,  70, 15, 1),
('7501000000127', 'Piroxicam',          'Antiinflamatorio',                2, 2, 'Caja 20 cápsulas', '20mg',   28.00,  60.00, 110, 20, 0),
('7501000000134', 'Indometacina',       'Antiinflamatorio',                2, 1, 'Caja 30 cápsulas', '25mg',   30.00,  65.00,  90, 20, 1),
('7501000000141', 'Nimesulida',         'Antiinflamatorio',                2, 2, 'Caja 20 tabletas', '100mg',  40.00,  90.00,  75, 15, 1),
('7501000000158', 'Piroxicam gel',      'Antiinflamatorio tópico',         2, 4, 'Tubo 60g',         '0.5%',   45.00, 100.00,  60, 15, 0),
-- Antibióticos (8)
('7501000000202', 'Amoxicilina',        'Antibiótico penicilínico',        3, 1, 'Caja 21 cápsulas', '500mg',  60.00, 135.00,  80, 20, 1),
('7501000000219', 'Azitromicina',       'Antibiótico macrólido',           3, 2, 'Caja 6 tabletas',  '500mg', 120.00, 260.00,  50, 15, 1),
('7501000000226', 'Ciprofloxacino',     'Antibiótico fluoroquinolona',     3, 3, 'Caja 14 tabletas', '500mg',  90.00, 195.00,  55, 15, 1),
('7501000000233', 'Cefalexina',         'Antibiótico cefalosporina',       3, 1, 'Caja 20 cápsulas', '500mg',  70.00, 155.00,  65, 15, 1),
('7501000000240', 'Doxiciclina',        'Antibiótico tetraciclina',        3, 2, 'Caja 20 cápsulas', '100mg',  60.00, 135.00,  60, 15, 1),
('7501000000257', 'Claritromicina',     'Antibiótico macrólido',           3, 3, 'Caja 14 tabletas', '500mg', 110.00, 240.00,  45, 15, 1),
('7501000000264', 'Metronidazol',       'Antibiótico y antiparasitario',   3, 1, 'Caja 30 tabletas', '500mg',  55.00, 120.00,  70, 20, 1),
('7501000000271', 'Amoxicilina-Clavulánico', 'Antibiótico combinado',      3, 3, 'Caja 14 tabletas', '875mg', 180.00, 395.00,  40, 10, 1),
-- Antihistamínicos (5)
('7501000000301', 'Loratadina',         'Antihistamínico',                 4, 1, 'Caja 10 tabletas', '10mg',   20.00,  50.00, 180, 25, 0),
('7501000000318', 'Cetirizina',         'Antihistamínico',                 4, 2, 'Caja 10 tabletas', '10mg',   22.00,  55.00, 160, 25, 0),
('7501000000325', 'Fexofenadina',       'Antihistamínico',                 4, 2, 'Caja 10 tabletas', '180mg',  45.00, 100.00, 100, 20, 0),
('7501000000332', 'Clorfeniramina',     'Antihistamínico',                 4, 1, 'Caja 20 tabletas', '4mg',    15.00,  40.00, 200, 30, 0),
('7501000000349', 'Desloratadina',      'Antihistamínico',                 4, 3, 'Caja 10 tabletas', '5mg',    55.00, 125.00,  90, 20, 0),
-- Gastrointestinales (7)
('7501000000400', 'Omeprazol',          'Inhibidor de bomba de protones',  5, 1, 'Caja 14 cápsulas', '20mg',   30.00,  70.00, 220, 25, 0),
('7501000000417', 'Omeprazol',          'Inhibidor de bomba de protones',  5, 1, 'Caja 28 cápsulas', '40mg',   55.00, 125.00, 100, 20, 1),
('7501000000424', 'Ranitidina',         'Antiácido H2',                    5, 2, 'Caja 20 tabletas', '150mg',  25.00,  60.00, 150, 25, 0),
('7501000000431', 'Metoclopramida',     'Procinético',                     5, 3, 'Caja 20 tabletas', '10mg',   35.00,  80.00,  90, 20, 1),
('7501000000448', 'Loperamida',         'Antidiarreico',                   5, 1, 'Caja 12 cápsulas', '2mg',    18.00,  45.00, 200, 25, 0),
('7501000000455', 'Lactulosa',          'Laxante',                         5, 2, 'Frasco 200ml',     '65%',    70.00, 155.00,  60, 15, 0),
('7501000000462', 'Simeticona',         'Antiflatulento',                  5, 4, 'Frasco 30ml',      '100mg',  40.00,  90.00, 110, 20, 0),
-- Vitaminas (7)
('7501000000500', 'Vitamina C',         'Suplemento vitamínico',           6, 4, 'Frasco 30 tabletas','500mg',  30.00,  75.00, 300, 30, 0),
('7501000000517', 'Vitamina D3',        'Suplemento vitamínico',           6, 4, 'Frasco 60 cápsulas','1000UI', 45.00, 105.00, 200, 25, 0),
('7501000000524', 'Complejo B',         'Suplemento vitamínico',           6, 4, 'Frasco 30 tabletas','N/A',    35.00,  85.00, 250, 30, 0),
('7501000000531', 'Vitamina E',         'Suplemento vitamínico',           6, 4, 'Frasco 30 cápsulas','400UI',  55.00, 125.00, 150, 25, 0),
('7501000000548', 'Ácido fólico',       'Suplemento vitamínico',           6, 1, 'Caja 20 tabletas', '5mg',    20.00,  50.00, 220, 30, 0),
('7501000000555', 'Hierro',             'Suplemento vitamínico',           6, 2, 'Caja 30 tabletas', '325mg',  35.00,  80.00, 180, 25, 0),
('7501000000562', 'Multivitamínico',    'Suplemento completo',             6, 4, 'Frasco 60 tabletas','N/A',    60.00, 140.00, 180, 25, 0),
-- Dermatológicos (6)
('7501000000600', 'Hidrocortisona',     'Corticoide tópico',               7, 5, 'Tubo 30g',         '1%',     35.00,  85.00, 120, 20, 0),
('7501000000617', 'Clotrimazol',        'Antifúngico tópico',              7, 5, 'Tubo 30g',         '1%',     30.00,  75.00, 140, 20, 0),
('7501000000624', 'Mupirocina',         'Antibiótico tópico',              7, 5, 'Tubo 15g',         '2%',     90.00, 195.00,  70, 15, 1),
('7501000000631', 'Permetrina',         'Antiparasitario tópico',          7, 3, 'Frasco 60ml',      '5%',     60.00, 135.00,  90, 15, 1),
('7501000000648', 'Benzoato de bencilo','Antiparasitario tópico',          7, 1, 'Frasco 60ml',      '25%',    40.00,  95.00, 100, 20, 0),
('7501000000655', 'Ketoconazol',        'Antifúngico',                     7, 5, 'Tubo 30g',         '2%',     55.00, 120.00,  80, 15, 1);

INSERT INTO medicamentos 
    (id_medicamento, codigo_barras, nombre, descripcion, id_categoria, id_laboratorio, 
     presentacion, concentracion, precio_compra, precio_venta, stock, stock_minimo, requiere_receta) 
VALUES
(47, '7501000000047', 'Meloxicam 15mg', 'Antiinflamatorio no esteroideo', 2, 1, 
 'Caja 10 tabletas', '15mg', 30.00, 55.00, 80, 10, 1),
(48, '7501000000048', 'Diclofenaco 100mg', 'Antiinflamatorio de liberación prolongada', 2, 1, 
 'Caja 20 tabletas', '100mg', 30.00, 55.00, 60, 10, 1),
(49, '7501000000049', 'Celecoxib 200mg', 'Antiinflamatorio selectivo COX-2', 2, 3, 
 'Caja 30 cápsulas', '200mg', 55.00, 95.00, 40, 8, 1),
(50, '7501000000050', 'Ketorolaco 10mg', 'Analgésico potente', 1, 2, 
 'Caja 10 tabletas', '10mg', 45.00, 80.00, 50, 10, 1);

-- ============================================================
-- 8. CLIENTES (30)
-- ============================================================
-- Clientes 1-10: con password_hash (usan el portal online)
-- Clientes 11-30: sin password (solo compras locales)
INSERT INTO clientes (nombre, apellido, email, telefono, id_pais, fecha_nacimiento, puntos_lealtad, password_hash) VALUES
('Sofía',    'Ramírez',   'sofia.ramirez@mail.test',   '5552000001', (SELECT id_pais FROM paises WHERE iso2='MX'), '1995-03-15',  150, '$2y$10$PLACEHOLDER_REPLACE_WITH_REAL_HASH_00000000000000000000010'),
('Diego',    'Torres',    'diego.torres@mail.test',    '5552000002', (SELECT id_pais FROM paises WHERE iso2='MX'), '1988-07-22',   80, '$2y$10$PLACEHOLDER_REPLACE_WITH_REAL_HASH_00000000000000000000011'),
('Valeria',  'Ortiz',     'valeria.ortiz@mail.test',   '5552000003', (SELECT id_pais FROM paises WHERE iso2='MX'), '2000-11-02',  200, '$2y$10$PLACEHOLDER_REPLACE_WITH_REAL_HASH_00000000000000000000012'),
('Andrés',   'Castro',    'andres.castro@mail.test',   '5552000004', (SELECT id_pais FROM paises WHERE iso2='MX'), '1992-05-10',   50, '$2y$10$PLACEHOLDER_REPLACE_WITH_REAL_HASH_00000000000000000000013'),
('Isabella', 'Vargas',    'isabella.vargas@mail.test', '5552000005', (SELECT id_pais FROM paises WHERE iso2='CO'), '1993-09-30',  320, '$2y$10$PLACEHOLDER_REPLACE_WITH_REAL_HASH_00000000000000000000014'),
('Sebastián','Mendoza',   'sebastian.mendoza@mail.test','5552000006',(SELECT id_pais FROM paises WHERE iso2='MX'), '1985-12-15',  100, '$2y$10$PLACEHOLDER_REPLACE_WITH_REAL_HASH_00000000000000000000015'),
('Camila',   'Ruiz',      'camila.ruiz@mail.test',     '5552000007', (SELECT id_pais FROM paises WHERE iso2='MX'), '1997-08-20',  250, '$2y$10$PLACEHOLDER_REPLACE_WITH_REAL_HASH_00000000000000000000016'),
('Mateo',    'Flores',    'mateo.flores@mail.test',    '5552000008', (SELECT id_pais FROM paises WHERE iso2='AR'), '1990-04-12',   75, '$2y$10$PLACEHOLDER_REPLACE_WITH_REAL_HASH_00000000000000000000017'),
('Daniela',  'Soto',      'daniela.soto@mail.test',    '5552000009', (SELECT id_pais FROM paises WHERE iso2='MX'), '1988-06-25',  400, '$2y$10$PLACEHOLDER_REPLACE_WITH_REAL_HASH_00000000000000000000018'),
('Lucas',    'Navarro',   'lucas.navarro@mail.test',   '5552000010', (SELECT id_pais FROM paises WHERE iso2='MX'), '1995-01-18',   30, '$2y$10$PLACEHOLDER_REPLACE_WITH_REAL_HASH_00000000000000000000019'),
('Ana',      'García',    'ana.garcia@mail.test',      '5552000011', (SELECT id_pais FROM paises WHERE iso2='MX'), '1975-03-08',  500, NULL),
('Luis',     'López',     'luis.lopez@mail.test',      '5552000012', (SELECT id_pais FROM paises WHERE iso2='MX'), '1980-07-14',  200, NULL),
('María',    'Martínez',  'maria.martinez@mail.test',  '5552000013', (SELECT id_pais FROM paises WHERE iso2='MX'), '1968-09-22',  750, NULL),
('Carlos',   'Pérez',     'carlos.perez@mail.test',    '5552000014', (SELECT id_pais FROM paises WHERE iso2='ES'), '1982-11-05',  120, NULL),
('Laura',    'Hernández', 'laura.hdz@mail.test',       '5552000015', (SELECT id_pais FROM paises WHERE iso2='MX'), '1990-02-28',  300, NULL),
('Pedro',    'Ruiz',      'pedro.ruiz@mail.test',      '5552000016', (SELECT id_pais FROM paises WHERE iso2='MX'), '1978-05-17',   85, NULL),
('Elena',    'Torres',    'elena.torres@mail.test',    '5552000017', (SELECT id_pais FROM paises WHERE iso2='MX'), '1985-08-09',  450, NULL),
('Jorge',    'Flores',    'jorge.flores@mail.test',    '5552000018', (SELECT id_pais FROM paises WHERE iso2='MX'), '1970-12-03',  180, NULL),
('Sofía',    'García',    'sofia.garcia@mail.test',    '5552000019', (SELECT id_pais FROM paises WHERE iso2='AR'), '1992-04-21',   60, NULL),
('Diego',    'López',     'diego.lopez@mail.test',     '5552000020', (SELECT id_pais FROM paises WHERE iso2='MX'), '1988-10-14',  620, NULL),
('Natalia',  'Ramírez',   'natalia.ramirez@mail.test', '5552000021', (SELECT id_pais FROM paises WHERE iso2='CO'), '1995-06-30',   95, NULL),
('Andrés',   'Castro',    'andres.castro2@mail.test',  '5552000022', (SELECT id_pais FROM paises WHERE iso2='MX'), '1983-01-07',  210, NULL),
('Valeria',  'Ortiz',     'valeria.ortiz2@mail.test',  '5552000023', (SELECT id_pais FROM paises WHERE iso2='MX'), '1997-11-19',   40, NULL),
('Sebastián','Mendoza',   'sebastian.m2@mail.test',    '5552000024', (SELECT id_pais FROM paises WHERE iso2='MX'), '1986-03-25',  380, NULL),
('Isabella', 'Vargas',    'isabella.v2@mail.test',     '5552000025', (SELECT id_pais FROM paises WHERE iso2='MX'), '1991-08-12',  130, NULL),
('Camila',   'Ruiz',      'camila.ruiz2@mail.test',    '5552000026', (SELECT id_pais FROM paises WHERE iso2='MX'), '1994-05-03',  270, NULL),
('Mateo',    'Flores',    'mateo.flores2@mail.test',   '5552000027', (SELECT id_pais FROM paises WHERE iso2='MX'), '1979-09-28',   55, NULL),
('Daniela',  'Soto',      'daniela.soto2@mail.test',   '5552000028', (SELECT id_pais FROM paises WHERE iso2='MX'), '1987-02-16',  340, NULL),
('Lucas',    'Navarro',   'lucas.navarro2@mail.test',  '5552000029', (SELECT id_pais FROM paises WHERE iso2='MX'), '1984-07-09',  165, NULL),
('Valentina','Rojas',     'valentina.rojas@mail.test', '5552000030', (SELECT id_pais FROM paises WHERE iso2='MX'), '1993-12-22',   70, NULL);

-- ============================================================
-- 9. DIRECCIONES_CLIENTE (15) — para clientes con portal online
-- ============================================================
INSERT INTO direcciones_cliente (id_cliente, alias, calle, colonia, ciudad, estado_provincia, codigo_postal, id_pais, es_predeterminada) VALUES
(1, 'Casa',    'Av. Reforma 123, Int. 4B',  'Centro',       'CDMX',         'CDMX',          '06000', (SELECT id_pais FROM paises WHERE iso2='MX'), 1),
(1, 'Oficina', 'Paseo de la Reforma 456',    'Juárez',       'CDMX',         'CDMX',          '06600', (SELECT id_pais FROM paises WHERE iso2='MX'), 0),
(2, 'Casa',    'Calle 5 de Mayo 78',         'La Paz',       'Puebla',       'Puebla',        '72000', (SELECT id_pais FROM paises WHERE iso2='MX'), 1),
(3, 'Casa',    'Av. Chapultepec 321',        'Americana',    'Guadalajara',  'Jalisco',       '44100', (SELECT id_pais FROM paises WHERE iso2='MX'), 1),
(4, 'Casa',    'Blvd. Kukulcán Km 9',        'Zona Hotelera','Cancún',       'Quintana Roo',  '77500', (SELECT id_pais FROM paises WHERE iso2='MX'), 1),
(5, 'Casa',    'Carrera 7 # 45-12',          'Chapinero',    'Bogotá',       'Cundinamarca',  '110111',(SELECT id_pais FROM paises WHERE iso2='CO'), 1),
(6, 'Casa',    'Av. Juárez 200',             'Centro',       'Monterrey',    'Nuevo León',    '64000', (SELECT id_pais FROM paises WHERE iso2='MX'), 1),
(7, 'Casa',    'Insurgentes Sur 1500',       'Del Valle',    'CDMX',         'CDMX',          '03100', (SELECT id_pais FROM paises WHERE iso2='MX'), 1),
(8, 'Casa',    'Av. Corrientes 1234',        'San Nicolás',  'Buenos Aires', 'CABA',          'C1042', (SELECT id_pais FROM paises WHERE iso2='AR'), 1),
(9, 'Casa',    'Av. Vallarta 2456',          'Arcos',        'Guadalajara',  'Jalisco',       '44500', (SELECT id_pais FROM paises WHERE iso2='MX'), 1),
(9, 'Oficina', 'Av. Américas 800',           'Country Club', 'Guadalajara',  'Jalisco',       '44610', (SELECT id_pais FROM paises WHERE iso2='MX'), 0),
(10,'Casa',    'Calle Hidalgo 45',           'Centro',       'Querétaro',    'Querétaro',     '76000', (SELECT id_pais FROM paises WHERE iso2='MX'), 1),
(2, 'Trabajo', 'Av. 5 de Mayo 500',          'Centro',       'Puebla',       'Puebla',        '72000', (SELECT id_pais FROM paises WHERE iso2='MX'), 0),
(3, 'Casa 2',  'Calle Morelos 230',          'Centro',       'Guadalajara',  'Jalisco',       '44100', (SELECT id_pais FROM paises WHERE iso2='MX'), 0),
(5, 'Casa 2',  'Calle 85 # 12-34',           'Chicó',        'Bogotá',       'Cundinamarca',  '110221',(SELECT id_pais FROM paises WHERE iso2='CO'), 0);

-- ============================================================
-- 10. COMPRAS (10) + DETALLE_COMPRAS (30)
-- ============================================================
INSERT INTO compras (folio, id_proveedor, id_usuario, fecha_compra, subtotal, iva, total, estado, fecha_recepcion) VALUES
('C-2026-0001', 1, 1, DATE_SUB(NOW(), INTERVAL 90 DAY), 13000.00, 2080.00, 15080.00, 'recibida', DATE_SUB(NOW(), INTERVAL 88 DAY)),
('C-2026-0002', 2, 1, DATE_SUB(NOW(), INTERVAL 80 DAY),  9000.00, 1440.00, 10440.00, 'recibida', DATE_SUB(NOW(), INTERVAL 78 DAY)),
('C-2026-0003', 3, 1, DATE_SUB(NOW(), INTERVAL 70 DAY), 20000.00, 3200.00, 23200.00, 'recibida', DATE_SUB(NOW(), INTERVAL 67 DAY)),
('C-2026-0004', 4, 1, DATE_SUB(NOW(), INTERVAL 60 DAY),  5500.00,  880.00,  6380.00, 'recibida', DATE_SUB(NOW(), INTERVAL 58 DAY)),
('C-2026-0005', 5, 1, DATE_SUB(NOW(), INTERVAL 50 DAY),  7500.00, 1200.00,  8700.00, 'recibida', DATE_SUB(NOW(), INTERVAL 48 DAY)),
('C-2026-0006', 6, 1, DATE_SUB(NOW(), INTERVAL 40 DAY), 11000.00, 1760.00, 12760.00, 'recibida', DATE_SUB(NOW(), INTERVAL 38 DAY)),
('C-2026-0007', 1, 1, DATE_SUB(NOW(), INTERVAL 30 DAY), 14500.00, 2320.00, 16820.00, 'recibida', DATE_SUB(NOW(), INTERVAL 28 DAY)),
('C-2026-0008', 2, 1, DATE_SUB(NOW(), INTERVAL 20 DAY),  8000.00, 1280.00,  9280.00, 'recibida', DATE_SUB(NOW(), INTERVAL 18 DAY)),
('C-2026-0009', 3, 1, DATE_SUB(NOW(), INTERVAL 10 DAY), 12500.00, 2000.00, 14500.00, 'pendiente', NULL),
('C-2026-0010', 5, 1, DATE_SUB(NOW(), INTERVAL  5 DAY),  6500.00, 1040.00,  7540.00, 'pendiente', NULL);

INSERT INTO detalle_compras (id_compra, id_medicamento, cantidad, precio_unitario, subtotal) VALUES
(1, 1, 100, 15.00, 1500.00), (1, 3, 100, 20.00, 2000.00), (1, 30, 100, 30.00, 3000.00), (1, 38, 200, 30.00, 6000.00), (1, 44, 25, 20.00, 500.00),
(2, 2, 100, 25.00, 2500.00), (2, 4, 100, 18.00, 1800.00), (2, 24, 50, 22.00, 1100.00), (2, 40, 100, 35.00, 3500.00),
(3, 15, 100, 60.00, 6000.00), (3, 16, 50, 120.00, 6000.00), (3, 17, 50, 90.00, 4500.00), (3, 21, 50, 55.00, 2750.00),
(4, 29, 50, 30.00, 1500.00), (4, 41, 50, 35.00, 1750.00), (4, 50, 50, 45.00, 2250.00),
(5, 5, 50, 30.00, 1500.00), (5, 12, 50, 45.00, 2250.00), (5, 25, 50, 45.00, 2250.00), (5, 33, 50, 15.00, 750.00),
(6, 18, 50, 70.00, 3500.00), (6, 19, 50, 60.00, 3000.00), (6, 20, 50, 55.00, 2750.00), (6, 22, 50, 55.00, 2750.00),
(7, 1, 200, 15.00, 3000.00), (7, 3, 150, 20.00, 3000.00), (7, 6, 50, 45.00, 2250.00), (7, 7, 50, 50.00, 2500.00), (7, 32, 100, 25.00, 2500.00),
(8, 31, 100, 30.00, 3000.00), (8, 32, 100, 25.00, 2500.00), (8, 45, 50, 35.00, 1750.00), (8, 48, 50, 30.00, 1500.00),
(9, 2, 200, 25.00, 5000.00), (9, 24, 50, 22.00, 1100.00), (9, 34, 50, 22.00, 1100.00), (9, 35, 100, 45.00, 4500.00),
(10, 28, 50, 30.00, 1500.00), (10, 36, 50, 35.00, 1750.00), (10, 47, 50, 35.00, 1750.00), (10, 46, 50, 30.00, 1500.00);

-- ============================================================
-- NOTA SOBRE CONTRASEÑAS
-- ============================================================
-- Los password_hash son PLACEHOLDERS. Para activar el login,
-- ejecutar el siguiente script PHP (guardar como
-- scripts/01_generate_passwords.php y ejecutar con el navegador
-- o con `php scripts/01_generate_passwords.php`):
--
-- <?php
-- $pdo = new PDO('mysql:host=localhost;dbname=vidaplus;charset=utf8mb4', 'root', '');
-- $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
--
-- $usuarios = [
--     [1, 'admin123'],
--     [2, 'farma123'],
--     [3, 'farma123'],
--     [4, 'caja123'],
--     [5, 'caja123'],
--     [6, 'audit123'],
-- ];
--
-- foreach ($usuarios as [$id, $pwd]) {
--     $hash = password_hash($pwd, PASSWORD_DEFAULT);
--     $stmt = $pdo->prepare('UPDATE usuarios SET password_hash = ? WHERE id_usuario = ?');
--     $stmt->execute([$hash, $id]);
-- }
--
-- $clientes = [
--     [1, 'cliente123'], [2, 'cliente123'], [3, 'cliente123'],
--     [4, 'cliente123'], [5, 'cliente123'], [6, 'cliente123'],
--     [7, 'cliente123'], [8, 'cliente123'], [9, 'cliente123'],
--     [10, 'cliente123'],
-- ];
--
-- foreach ($clientes as [$id, $pwd]) {
--     $hash = password_hash($pwd, PASSWORD_DEFAULT);
--     $stmt = $pdo->prepare('UPDATE clientes SET password_hash = ? WHERE id_cliente = ?');
--     $stmt->execute([$hash, $id]);
-- }
--
-- echo "Contraseñas actualizadas correctamente\n";
-- ?>
--
-- Contraseñas resultantes:
--   Usuarios: admin/admin123, farmaceutico1/farma123, farmaceutico2/farma123,
--             cajero1/caja123, cajero2/caja123, auditor1/audit123
--   Clientes: todos los del 1 al 10 tienen contraseña "cliente123"