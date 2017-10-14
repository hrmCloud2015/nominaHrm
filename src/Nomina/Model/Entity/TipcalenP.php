<?php
/*
 * STANDAR DE NISSI MODELO A LA BD MAESTROS
 * 
 */
namespace Nomina\Model\Entity;

use Zend\Db\TableGateway\TableGateway;
use Zend\Db\Adapter\Adapter;

class TipcalenP extends TableGateway
{
    private $idcal;
    private $mi;
    private $mf;
    private $di;
    private $df;
    private $orden;
    private $id;
        
    public function __construct(Adapter $adapter = null, $databaseSchema = null, ResultSet $selectResultPrototype = null)
    {
        return parent::__construct('n_tip_calendario_p', $adapter, $databaseSchema,$selectResultPrototype);
    }

    private function cargaAtributos($datos=array())
    {
        $this->idcal = $datos["idcal"];    
        $this->mi    = $datos["mi"];   
        $this->mf    = $datos["mf"];  
        $this->di    = $datos["di"];  
        $this->df    = $datos["df"];  
        $this->orden = $datos["o"];  
        $this->id    = $datos["id"];  
    }
    
    public function getRegistro()
    {
       $datos = $this->select();
       return $datos->toArray();
    }
    
    public function actRegistro($data=array())
    {
       self::cargaAtributos($data);
       $datos=array
       (
           'idCal'   => $this->idcal,
           'mesI'    => $this->mi,
           'mesF'    => $this->mf,
           'diaI'    => $this->di,
           'diaF'    => $this->df,
           'orden'  => $this->orden,
        );
        if ($this->id==0)
           $this->insert($datos);
        else
           $this->update($datos, array("id"=>$this->id)); 

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
