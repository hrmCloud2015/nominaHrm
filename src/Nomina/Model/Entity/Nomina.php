<?php
/*
 * STANDAR DE NISSI MODELO A LA BD MAESTROS
 * 
 */
namespace Nomina\Model\Entity;

use Zend\Db\TableGateway\TableGateway;
use Zend\Db\Adapter\Adapter;

class Nomina extends TableGateway
{
    private $id;
    private $idgrupo;
    private $idtcal;
    private $tipo;
        
    public function __construct(Adapter $adapter = null, $databaseSchema = null, ResultSet $selectResultPrototype = null)
    {
        return parent::__construct('n_nomina', $adapter, $databaseSchema,$selectResultPrototype);
    }

    private function cargaAtributos($datos=array())
    {
        $this->id       = $datos["id"];    
        $this->idgrupo  = $datos["idGrupo"];   
        $this->idtcal   = $datos["idCal"];   
        $this->tipo     = $datos["tipo"];   
    }
    
    public function getRegistro()
    {
       $datos = $this->select();
       return $datos->toArray();
    }
    
    public function actRegistro($data=array(),$fechai,$fechaf)
    {
       self::cargaAtributos($data);
       $id = $this->id;
       $datos=array
       (
           'idGrupo' => $this->idgrupo,
           'idCal'   => $this->idtcal,    
           'idTnom'  => $this->tipo,
           'fechaI'  => $fechai,
           'fechaF'  => $fechaf
        );
        
       if ($id==0) // Nuevo registro
          $this->insert($datos);
       else // Mdificar registro
          $this->update($datos, array('id' => $id));
    }
    
    public function getRegistroId($id)
    {
       $id  = (int) $id;
       $rowset = $this->select(array('id' => $id));
       $row = $rowset->current();
      
       if (!$row) {
          throw new \Exception("No hay registros asociados al valor $id");
       }
       return $row;
     }        
     public function delRegistro($id)
     {
       $this->delete(array('id' => $id));               
     }
}
?>
