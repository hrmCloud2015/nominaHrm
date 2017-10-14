<?php
/*
 * STANDAR DE NISSI MODELO A LA BD MAESTROS
 * 
 */
namespace Nomina\Model\Entity;

use Zend\Db\TableGateway\TableGateway;
use Zend\Db\Adapter\Adapter;

class Prestan extends TableGateway
{
    private $id;
        
    public function __construct(Adapter $adapter = null, $databaseSchema = null, ResultSet $selectResultPrototype = null)
    {
        return parent::__construct('n_prestamos_tn', $adapter, $databaseSchema,$selectResultPrototype);
    }
       
    public function getRegistro()
    {
       $datos = $this->select();
       return $datos->toArray();
    }     
    public function actRegistro( $idPres,$idTnom,$valor,$cuotas )
    {
       $datos=array
       (
           'idPres'  => $idPres,
           'idTnom'  => $idTnom,
           'valor'   => $valor,
           'cuotas'  => $cuotas,
           'valCuota'  => $valor / $cuotas,
        );
       //if ($n==0) // Nuevo registro
          $this->insert($datos);

    }    

}
?>
