<?php

namespace Diprotec\ERP\Clients;

use Diprotec\ERP\Interfaces\ClientInterface;

class MockClient implements ClientInterface
{

    private $mock_data_path;

    public function __construct()
    {
        $this->mock_data_path = DIPROTEC_ERP_PATH . 'mock-data/';
    }

    /**
     * Get products from ERP (Mock).
     * Updated to return new JSON v2 structure.
     *
     * @param string|null $modified_after Timestamp to filter products.
     * @return array
     */
    public function getProducts(?string $modified_after = null): array
    {
        // v2.0 Mock Data hardcoded or loaded from file to match JSON_Consulta_15-01.json
        // For simplicity and robustness, I'll return the array structure directly here matching the guide examples

        return [
            "Estado" => 200,
            "Respuesta" => "TRANSACCION_OK",
            "Data" => (function ($page = 1, $limit = 50) {
                // Mock data from PRODUCTOS.xlsx (Parsed)
                // Images: Just filenames, ImageHandler looks in wp-content/uploads/erp-images/
                $products = [
                    [
                        'CategoriaId' => 'PC020',
                        'CategoriaNombre' => 'IMPRESORAS',
                        'SubCategoriaId' => 'PSC0106',
                        'SubCategoriaNombre' => 'ACCESORIOS',
                        'Id' => 'PRO-0001000',
                        'Partnumber' => '105909-020',
                        'Atributos' => '',
                        'Descripcion' => 'ZEBRACARD. MAIN POWER SUPPLY. 100 WATT (P/N 105909-020).',
                        'ImagenPpal' => 'PRO-0001000.png',
                        'Imagen01' => '',
                        'Imagen02' => '',
                        'Imagen03' => '',
                        'Imagen04' => '',
                        'Imagen05' => '',
                        'Imagen06' => '',
                        'PrecioLista' => 45000,
                        'PrecioOferta' => 0,
                        'Stock' => 1,
                        'MarcaNombre' => 'ZEBRA'
                    ],
                    [
                        'CategoriaId' => 'PC020',
                        'CategoriaNombre' => 'IMPRESORAS',
                        'SubCategoriaId' => 'PSC0017',
                        'SubCategoriaNombre' => 'CREDENCIALES',
                        'Id' => 'PRO-0001001',
                        'Partnumber' => 'P100I-0000A-ID0',
                        'Atributos' => 'ESTANDAR',
                        'Descripcion' => 'ZEBRACARD. IMPRESORA DE TARJETAS DE PVC. MODELO P100I. ESTANDAR. SINGLE SIDE. CONEXION USB. DISPLAY LCD. (P/N P100I-0000A-ID0).',
                        'ImagenPpal' => 'PRO-0001001.png',
                        'Imagen01' => '',
                        'Imagen02' => '',
                        'Imagen03' => '',
                        'Imagen04' => '',
                        'Imagen05' => '',
                        'Imagen06' => '',
                        'PrecioLista' => 1250000,
                        'PrecioOferta' => 0,
                        'Stock' => 1,
                        'MarcaNombre' => 'ZEBRA'
                    ],
                    [
                        'CategoriaId' => 'PC028',
                        'CategoriaNombre' => 'REPUESTOS',
                        'SubCategoriaId' => 'PSC0144',
                        'SubCategoriaNombre' => 'IMPRESORAS',
                        'Id' => 'PRO-0001002',
                        'Partnumber' => 'AP04-00074V',
                        'Atributos' => 'SRP-350PLUS',
                        'Descripcion' => 'BIXOLON. MAIN LOGIC BOARD. MODELO SRP-350PLUS. (P/N AP04-00074V).',
                        'ImagenPpal' => 'PRO-0001002.png',
                        'Imagen01' => '',
                        'Imagen02' => '',
                        'Imagen03' => '',
                        'Imagen04' => '',
                        'Imagen05' => '',
                        'Imagen06' => '',
                        'PrecioLista' => 85000,
                        'PrecioOferta' => 0,
                        'Stock' => 1,
                        'MarcaNombre' => 'BIXOLON'
                    ],
                    [
                        'CategoriaId' => 'PC020',
                        'CategoriaNombre' => 'IMPRESORAS',
                        'SubCategoriaId' => 'PSC0207',
                        'SubCategoriaNombre' => 'TEMPORAL', // Using Temporal as per CSV until clearer mapping
                        'Id' => 'PRO-0001005',
                        'Partnumber' => 'P110I-0000A-ID0',
                        'Atributos' => 'PVC',
                        'Descripcion' => 'ZEBRACARD. IMPRESORA DE TARJETAS DE PVC. MODELO P110I. ESTANDAR. COLOR. SINGLE SIDE. LCD DISPLAY. 16MB RAM. (P/N P110I-0000A-ID0).',
                        'ImagenPpal' => 'PRO-0001005.png',
                        'Imagen01' => '',
                        'Imagen02' => '',
                        'Imagen03' => '',
                        'Imagen04' => '',
                        'Imagen05' => '',
                        'Imagen06' => '',
                        'PrecioLista' => 890000,
                        'PrecioOferta' => 850000,
                        'Stock' => 1,
                        'MarcaNombre' => 'ZEBRA'
                    ],
                    [
                        'CategoriaId' => 'PC019',
                        'CategoriaNombre' => 'COMPUTO MÓVIL',
                        'SubCategoriaId' => 'PSC0195',
                        'SubCategoriaNombre' => 'ACCESORIOS',
                        'Id' => 'PRO-0001006',
                        'Partnumber' => '6100-HB',
                        'Atributos' => '6100',
                        'Descripcion' => 'HONEYWELL. CUNA DE COMUNICACION Y CARGA. PARA MODELO DOLPHIN 6100. COMUNICACION USB/RS232. (P/N 6100-HB).',
                        'ImagenPpal' => 'PRO-0001006.png',
                        'Imagen01' => '',
                        'Imagen02' => '',
                        'Imagen03' => '',
                        'Imagen04' => '',
                        'Imagen05' => '',
                        'Imagen06' => '',
                        'PrecioLista' => 65000,
                        'PrecioOferta' => 0,
                        'Stock' => 1,
                        'MarcaNombre' => 'HONEYWELL'
                    ],
                    [
                        'CategoriaId' => 'PC020',
                        'CategoriaNombre' => 'IMPRESORAS',
                        'SubCategoriaId' => 'PSC0017',
                        'SubCategoriaNombre' => 'CREDENCIALES',
                        'Id' => 'PRO-0001010',
                        'Partnumber' => 'P110I-0000A-IDB-OPEN',
                        'Atributos' => 'KIT/OPEN BOX',
                        'Descripcion' => 'ZEBRACARD. P110I KIT. INCLUYE: IMPRESORA P110I ESTANDAR SINGLE SIDED. QUICKCARD SOFTWARE BASICO. 200 PVC CARD BLANCAS. RIBBON COLOR. WEBCAM USB. MINI TRIPODE. PRODUCTO/OPEN BOX (P/N P110I-0000A-IDB).',
                        'ImagenPpal' => 'PRO-0001010.png',
                        'Imagen01' => '',
                        'Imagen02' => '',
                        'Imagen03' => '',
                        'Imagen04' => '',
                        'Imagen05' => '',
                        'Imagen06' => '',
                        'PrecioLista' => 1100000,
                        'PrecioOferta' => 0,
                        'Stock' => 1,
                        'MarcaNombre' => 'ZEBRA'
                    ],
                    [
                        'CategoriaId' => 'PC022',
                        'CategoriaNombre' => 'INSUMOS',
                        'SubCategoriaId' => 'PSC0132',
                        'SubCategoriaNombre' => 'CREDENCIALES',
                        'Id' => 'PRO-0001011',
                        'Partnumber' => '104523-010',
                        'Atributos' => 'ADHESIVA',
                        'Descripcion' => 'ZEBRACARD. 0.10 MIL. ADHESIVE BACK CARDS. CON LINER. BOX 500/U. (P/N 104523-010).',
                        'ImagenPpal' => 'PRO-0001011.png',
                        'Imagen01' => '',
                        'Imagen02' => '',
                        'Imagen03' => '',
                        'Imagen04' => '',
                        'Imagen05' => '',
                        'Imagen06' => '',
                        'PrecioLista' => 45000,
                        'PrecioOferta' => 0,
                        'Stock' => 1,
                        'MarcaNombre' => 'ZEBRA'
                    ],
                    [
                        'CategoriaId' => 'PC019',
                        'CategoriaNombre' => 'COMPUTO MÓVIL',
                        'SubCategoriaId' => 'PSC0014',
                        'SubCategoriaNombre' => 'CON MANGO',
                        'Id' => 'PRO-0001012',
                        'Partnumber' => 'MC3090R-LC48S00GER',
                        'Atributos' => '1D/WLAN',
                        'Descripcion' => 'MOTOROLA. MC3090R. WLAN 802.11 A/B/G. ROTATING HEAD. LASER. COLOR. 64/64MB. WIN CE5.0. 48 KEY. BATTERY (P/N MC3090R-LC48S00GER).',
                        'ImagenPpal' => 'PRO-0001012.png',
                        'Imagen01' => '',
                        'Imagen02' => '',
                        'Imagen03' => '',
                        'Imagen04' => '',
                        'Imagen05' => '',
                        'Imagen06' => '',
                        'PrecioLista' => 980000,
                        'PrecioOferta' => 0,
                        'Stock' => 1,
                        'MarcaNombre' => 'MOTOROLA'
                    ],
                    [
                        'CategoriaId' => 'PC023',
                        'CategoriaNombre' => 'LECTORES',
                        'SubCategoriaId' => 'PSC0019',
                        'SubCategoriaNombre' => 'DE MESÓN',
                        'Id' => 'PRO-0001022',
                        'Partnumber' => '4800DR153I-0F00E',
                        'Atributos' => 'US',
                        'Descripcion' => 'HONEYWELL. ESCANNER PARA DIGITALIZAR DOCUMENTOS. MODELO 4800DR. (DOCUMENT READER). CONEXION USB. COLOR NEGRO. INCLUYE PEDESTAL Y BANDEJA. (P/N 4800DR153I-0F00E).',
                        'ImagenPpal' => 'PRO-0001022.png',
                        'Imagen01' => '',
                        'Imagen02' => '',
                        'Imagen03' => '',
                        'Imagen04' => '',
                        'Imagen05' => '',
                        'Imagen06' => '',
                        'PrecioLista' => 220000,
                        'PrecioOferta' => 0,
                        'Stock' => 1,
                        'MarcaNombre' => 'HONEYWELL'
                    ],
                    [
                        'CategoriaId' => 'PC022',
                        'CategoriaNombre' => 'INSUMOS',
                        'SubCategoriaId' => 'PSC0137',
                        'SubCategoriaNombre' => 'ETIQUETAS',
                        'Id' => 'PRO-0001023',
                        'Partnumber' => '10007205',
                        'Atributos' => 'POLY/R9680/3 INCH',
                        'Descripcion' => 'ZEBRA. ETIQUETA DE JOYA. CORTE MARIPOSA. POLIPRO 4000D. ROLLO/9680U. DIRECT THERMAL. CONO/3-INCH. COMPRA MIN./12ROLLOS. (P/N 10007205)',
                        'ImagenPpal' => 'PRO-0001023.png',
                        'Imagen01' => '',
                        'Imagen02' => '',
                        'Imagen03' => '',
                        'Imagen04' => '',
                        'Imagen05' => '',
                        'Imagen06' => '',
                        'PrecioLista' => 25000,
                        'PrecioOferta' => 0,
                        'Stock' => 1,
                        'MarcaNombre' => 'ZEBRA'
                    ],
                    [
                        'CategoriaId' => 'PC020',
                        'CategoriaNombre' => 'IMPRESORAS',
                        'SubCategoriaId' => 'PSC0023',
                        'SubCategoriaNombre' => 'ETIQUETAS',
                        'Id' => 'PRO-0001148',
                        'Partnumber' => 'P4D-0UG10000-00',
                        'Atributos' => '802.11B',
                        'Descripcion' => 'ZEBRA. PRINTER TRANSFER/THERMAL. MOVIL. P4T. 4 INCH. 203DPI. 8MB RAM. 16MB FLASH. COMUNICACION WIFI. USB. (P/N P4D-0UG10000-00).',
                        'ImagenPpal' => 'PRO-0001148.png',
                        'Imagen01' => '',
                        'Imagen02' => '',
                        'Imagen03' => '',
                        'Imagen04' => '',
                        'Imagen05' => '',
                        'Imagen06' => '',
                        'PrecioLista' => 840000,
                        'PrecioOferta' => 0,
                        'Stock' => 10,
                        'MarcaNombre' => 'ZEBRA'
                    ],
                    [
                        'CategoriaId' => 'PC020',
                        'CategoriaNombre' => 'IMPRESORAS',
                        'SubCategoriaId' => 'PSC0023',
                        'SubCategoriaNombre' => 'ETIQUETAS',
                        'Id' => 'PRO-000115',
                        'Partnumber' => 'GK42-102510-000',
                        'Atributos' => 'RS232/USB',
                        'Descripcion' => 'ZEBRA. THERMAL TRANSFER PRINTER. MODELO GK420T. 4-INCH ANCHO. 203DPI. EPL Y ZPL II. 5-INCH/SEG. CONEXION USB/SERIAL/CENTRONIX. (P/N GK42-102510-000)',
                        'ImagenPpal' => 'PRO-000115.png',
                        'Imagen01' => '',
                        'Imagen02' => '',
                        'Imagen03' => '',
                        'Imagen04' => '',
                        'Imagen05' => '',
                        'Imagen06' => '',
                        'PrecioLista' => 320000,
                        'PrecioOferta' => 299000,
                        'Stock' => 10,
                        'MarcaNombre' => 'ZEBRA'
                    ],
                    [
                        'CategoriaId' => 'PC020',
                        'CategoriaNombre' => 'IMPRESORAS',
                        'SubCategoriaId' => 'PSC0054',
                        'SubCategoriaNombre' => 'TICKET',
                        'Id' => 'PRO-000119',
                        'Partnumber' => 'C31C513153',
                        'Atributos' => 'RS232',
                        'Descripcion' => 'EPSON. IMPRESORA DE VALES MATRIZ DE PUNTO. MODELO TM-U220. INCLUYE CORTADOR. CONEXION SERIAL. COLOR NEGRO. (P/N C31C513153).',
                        'ImagenPpal' => 'PRO-000119.png',
                        'Imagen01' => '',
                        'Imagen02' => '',
                        'Imagen03' => '',
                        'Imagen04' => '',
                        'Imagen05' => '',
                        'Imagen06' => '',
                        'PrecioLista' => 180000,
                        'PrecioOferta' => 0,
                        'Stock' => 10,
                        'MarcaNombre' => 'EPSON'
                    ],
                    [
                        'CategoriaId' => 'PC020',
                        'CategoriaNombre' => 'IMPRESORAS',
                        'SubCategoriaId' => 'PSC0023',
                        'SubCategoriaNombre' => 'ETIQUETAS',
                        'Id' => 'PRO-0001201',
                        'Partnumber' => 'E-60X50X2-1-OPACO-R1000',
                        'Atributos' => '60X50X2/R1000/1 INCH/OPAQUE',
                        'Descripcion' => 'ETIQUETA. 60MM DE AVANCE X 50MM DE ANCHO X 2 COLUMNAS. CONO 1-INCH. OPACO. ROLLO DE 1.000 UNIDADES.',
                        'ImagenPpal' => 'PRO-0001201.png',
                        'Imagen01' => '',
                        'Imagen02' => '',
                        'Imagen03' => '',
                        'Imagen04' => '',
                        'Imagen05' => '',
                        'Imagen06' => '',
                        'PrecioLista' => 4500,
                        'PrecioOferta' => 0,
                        'Stock' => 4,
                        'MarcaNombre' => 'GENERICO'
                    ],
                ];

                return $products;
            })($page = 1, $limit = 50),
            "CodigoError" => null,
            "CorrelationId" => null
        ];
    }

    /**
     * Get stock for a specific SKU (Mock).
     */
    public function getStock(string $sku): array
    {
        $file_path = $this->mock_data_path . 'stock_responses.json';
        if (!file_exists($file_path)) {
            return ['available_qty' => 0, 'allow_backorder' => false];
        }
        $json_content = file_get_contents($file_path);
        $data = json_decode($json_content, true);
        return $data[$sku] ?? ['available_qty' => 0, 'allow_backorder' => false];
    }

    public function createOrder(array $order_payload): array
    {
        return ['status' => 'success', 'erp_id' => 'MOCK-' . rand(1000, 9999)];
    }

    // Stub for v2 compatible interface check if called
    public function getCustomerByRut($rut)
    {
        return [
            "success" => true,
            "data" => [
                "business_name" => "Empresa Mock S.A.",
                "giro" => "Venta de Tecnología",
                "addresses" => [
                    ["id" => "DIR1", "address" => "Av. Mock 123", "type" => "billing"],
                    ["id" => "DIR2", "address" => "Bodega Mock 456", "type" => "shipping"]
                ]
            ]
        ];
    }
}
