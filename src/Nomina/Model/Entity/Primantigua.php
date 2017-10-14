<?php
/*
 * STANDAR DE NISSI MODELO A LA BD MAESTROS
 * 
 */
namespace Nomina\Model\Entity;

use Zend\Db\TableGateway\TableGateway;
use Zend\Db\Adapter\Adapter;
use Principal\Model\LogFunc; // Traer datos de session activa y datos del pc 

class Primantigua extends TableGateway
{
    private $id;
    private $comen;
    private $tipo;
    private $formula;
    private $valor;
    
    public function __construct(Adapter $adapter = null, $databaseSchema = null, ResultSet $selectResultPrototype = null)
    {
        return parent::__construct('n_prima_anti', $adapter, $databaseSchema,$selectResultPrototype);
    }

    private function cargaAtributos($datos=array())
    {
        $this->id       = $datos["id"];    
        $this->comen    = $datos["comen"];  
        $this->tipo     = $datos["idConc"];  
        $this->formula  = $datos["formula"];  
        $this->valor    = $datos["ano"];  
    }
    
    public function getRegistro()
    {
       $datos = $this->select();
       return $datos->toArray();
    }
    
    public function actRegistro($data=array())
    {
       self::cargaAtributos($data);
       $id = $this->id;
       // Datos de transaccion
       $t = new LogFunc($this->adapter);
       $dt = $t->getDatLog();
       // ---        
       $datos=array
       (
           'comen'     => $this->comen,    
           'idConc'    => $this->tipo, 
           'fecDoc'    => $dt['fecSis'],
           'formula'   => $this->formula,
           'ano'       => $this->valor,
           'anual'     => $data['check2'],                      
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
