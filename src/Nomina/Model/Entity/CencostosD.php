<?php
/*
 * STANDAR DE NISSI MODELO A LA BD MAESTROS
 * 
 */
namespace Nomina\Model\Entity;

use Zend\Db\TableGateway\TableGateway;
use Zend\Db\Adapter\Adapter;

class CencostosD extends TableGateway
{
    private $id;
    private $nombre;
    
    public function __construct(Adapter $adapter = null, $databaseSchema = null, ResultSet $selectResultPrototype = null)
    {
        return parent::__construct('n_cencostos_d', $adapter, $databaseSchema,$selectResultPrototype);
    }

    public function getRegistro()
    {
       $datos = $this->select();
       return $datos->toArray();
    }
    
    public function actRegistro($idGdot, $idCcos )
    {
       $datos=array
       (
           'idCcos'  => $idCcos,
           'idGdot'  => $idGdot,
        );
       $this->insert($datos);
    }
    
    public function getRegistroId($id)
    {
       $id  = (int) $id;
       $datos = $this->select(array('idCcos' => $id));
       $row = $datos->toArray();
       return $row;
     }        
     public function delRegistro($id)
     {
       $this->delete(array('id' => $id));               
     }
}
?>
