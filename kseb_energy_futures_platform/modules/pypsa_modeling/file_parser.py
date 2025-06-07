"""
Parses the PyPSA input template Excel file for the PyPSA Modeling module.

This module contains functions to read and process data from various sheets
within the `pypsa_input_template.xlsx`. It extracts settings tables, component data,
time-series data, and cost/parameter information, structuring them into pandas
DataFrames for use in PyPSA model configuration and execution.
"""
import pandas as pd

# TODO: Add logging configuration if a platform-wide logger is established.
# For now, will use print for warnings/errors during parsing.

def extract_tables_from_sheet_pypsa(sheet_df: pd.DataFrame, sheet_name: str) -> dict:
    """
    Extracts multiple tables from a single sheet DataFrame based on tilde (~) markers.

    The function iterates through rows to find markers (e.g., "~TableName").
    Each marker signifies the start of a new table. The row immediately following
    the marker is treated as the header row for that table. Data rows are read
    until an empty row in the first column is encountered or another marker
    (signifying a new table) is found.

    Args:
        sheet_df (pd.DataFrame): The DataFrame representing the Excel sheet,
                                 read without headers (e.g., `pd.read_excel(..., header=None)`).
        sheet_name (str): The name of the sheet, used for logging/error messages.

    Returns:
        dict: A dictionary where keys are table names (derived from markers,
              e.g., "TableName" from "~TableName") and values are pandas
              DataFrames of the extracted tables.
    """
    if sheet_df.empty:
        print(f"Info: Sheet '{sheet_name}' is empty, no tables to extract.")
        return {}

    tables = {}
    current_table_name = None
    current_table_header_row_idx = -1
    current_table_data_start_row_idx = -1

    # Iterate through each row to identify markers and table boundaries
    for idx, row in sheet_df.iterrows():
        first_cell_value = str(row.iloc[0]).strip()

        is_marker = first_cell_value.startswith("~")
        is_empty_row_start = not first_cell_value # True if first cell is empty/NaN

        # If we encounter a new marker or end of sheet, and currently parsing a table, finalize previous table
        if (is_marker or idx == len(sheet_df) - 1) and current_table_name:
            data_end_row_idx = idx
            if idx == len(sheet_df) - 1 and not is_empty_row_start and not is_marker : # if it's the last row and not empty
                data_end_row_idx = idx + 1


            if current_table_data_start_row_idx < data_end_row_idx :
                table_df_data = sheet_df.iloc[current_table_data_start_row_idx:data_end_row_idx]
                if not table_df_data.empty:
                    headers = sheet_df.iloc[current_table_header_row_idx].astype(str).tolist()
                    # Ensure headers are unique if necessary, pandas handles basic cases
                    table_df_data.columns = headers
                    tables[current_table_name] = table_df_data.reset_index(drop=True)
                    # print(f"Extracted table '{current_table_name}' from sheet '{sheet_name}' with {len(table_df_data)} rows.")
                else:
                    print(f"Info: Table '{current_table_name}' in sheet '{sheet_name}' has headers but no data rows.")
                    headers = sheet_df.iloc[current_table_header_row_idx].astype(str).tolist()
                    tables[current_table_name] = pd.DataFrame(columns=headers)

            current_table_name = None # Reset for next table

        # If a new marker is found, start processing for a new table
        if is_marker:
            current_table_name = first_cell_value[1:] # Remove "~"
            current_table_header_row_idx = idx + 1
            current_table_data_start_row_idx = idx + 2 # Data starts after header
            # print(f"Found marker for table: '{current_table_name}' in sheet '{sheet_name}' at row {idx}.")
            if current_table_header_row_idx >= len(sheet_df):
                print(f"Warning: Marker '{current_table_name}' found at end of sheet '{sheet_name}', no header/data possible.")
                current_table_name = None # Invalid table start
            elif current_table_data_start_row_idx > len(sheet_df): # Header exists but no data rows possible
                 print(f"Info: Table '{current_table_name}' in sheet '{sheet_name}' has a header row but no data rows possible after it.")
                 headers = sheet_df.iloc[current_table_header_row_idx].astype(str).tolist()
                 tables[current_table_name] = pd.DataFrame(columns=headers)
                 current_table_name = None


    # Special case: if the last table goes to the very end of the sheet
    if current_table_name and current_table_data_start_row_idx < len(sheet_df):
        table_df_data = sheet_df.iloc[current_table_data_start_row_idx:]
        if not table_df_data.empty:
            headers = sheet_df.iloc[current_table_header_row_idx].astype(str).tolist()
            table_df_data.columns = headers
            tables[current_table_name] = table_df_data.reset_index(drop=True)
            # print(f"Extracted final table '{current_table_name}' from sheet '{sheet_name}' with {len(table_df_data)} rows.")
        else: # Header exists but no data rows
            print(f"Info: Final table '{current_table_name}' in sheet '{sheet_name}' has headers but no data rows.")
            headers = sheet_df.iloc[current_table_header_row_idx].astype(str).tolist()
            tables[current_table_name] = pd.DataFrame(columns=headers)


    if not tables:
        print(f"Warning: No tables with tilde (~) markers found in sheet '{sheet_name}'. If this is not the Settings sheet, this might be normal.")

    return tables


def parse_pypsa_input_template(file_path: str) -> dict | None:
    """
    Parses a PyPSA input template Excel file into a dictionary of DataFrames.

    The function reads various sheets expected in a PyPSA model template:
    - 'Settings': Special sheet with multiple tables marked by "~TableName".
    - Component Sheets: e.g., 'Buses', 'Generators', 'Lines', etc.
    - Time-Series Sheets: e.g., 'Demand', 'P_max_pu', etc.
    - Cost/Parameter Sheets: e.g., 'Lifetime', 'Capital_cost', etc.
    Any other sheets present in the file are also read and included.

    Args:
        file_path (str): The path to the PyPSA input template Excel file.

    Returns:
        dict | None: A dictionary where keys are sheet names (or table names
                     from the 'Settings' sheet) and values are pandas DataFrames.
                     Returns None if the file cannot be read or critical sheets
                     (like 'Settings' if expected to be marker-based) are problematic.
    """
    print(f"Parsing PyPSA input template from: {file_path}")
    parsed_data = {}

    try:
        xls = pd.ExcelFile(file_path)
        sheet_names = xls.sheet_names
    except FileNotFoundError:
        print(f"Error: PyPSA template file not found at '{file_path}'.")
        return None
    except Exception as e:
        print(f"Error opening PyPSA template file '{file_path}': {e}")
        return None

    # 1. Process 'Settings' sheet (special handling for multiple tables)
    settings_sheet_name = 'Settings' # Standard name
    if settings_sheet_name in sheet_names:
        try:
            # Read without headers to allow marker detection in any column, though typically first
            settings_df_raw = xls.parse(settings_sheet_name, header=None)
            parsed_data[settings_sheet_name] = extract_tables_from_sheet_pypsa(settings_df_raw, settings_sheet_name)
            print(f"Processed '{settings_sheet_name}' sheet with {len(parsed_data[settings_sheet_name])} tables.")
        except Exception as e:
            print(f"Error processing '{settings_sheet_name}' sheet: {e}. Storing as raw DataFrame if possible, else None.")
            try: # Fallback to reading as a normal sheet if marker parsing fails
                 parsed_data[settings_sheet_name] = xls.parse(settings_sheet_name)
            except Exception as e_raw:
                 print(f"Could not read '{settings_sheet_name}' sheet even as raw: {e_raw}")
                 parsed_data[settings_sheet_name] = None # Critical error if settings cannot be read
    else:
        print(f"Warning: PyPSA template is missing the crucial '{settings_sheet_name}' sheet.")
        parsed_data[settings_sheet_name] = None # Indicate missing critical sheet

    # Sheets to be read as single DataFrames directly
    # Define common categories of sheets expected in PyPSA templates
    component_sheets = ['Buses', 'Generators', 'New_Generators', 'New_Storage', 'Links', 'Lines', 'Transformers', 'Stores', 'StorageUnits']
    timeseries_sheets = ['Demand', 'P_max_pu', 'P_min_pu', 'Inflow', 'Hydro_Max_Energy_Monthly', 'Load'] # 'Load' is often used for demand
    cost_param_sheets = ['Lifetime', 'FOM', 'VOM', 'Fuel_cost', 'Startupcost', 'CO2_emission_factors', 'Capital_cost', 'WACC',
                         'Pipe_Line_Generators_p_max', 'Pipe_Line_Generators_p_min', 'Pipe_Line_Storage_p_min'] # Added more typical cost sheets

    all_categorized_sheets = component_sheets + timeseries_sheets + cost_param_sheets

    processed_sheet_names = {settings_sheet_name} # Keep track of sheets already processed

    for sheet_category_list, category_name in zip(
        [component_sheets, timeseries_sheets, cost_param_sheets],
        ["Component", "Time-Series", "Cost/Parameter"]
    ):
        for sheet_name in sheet_category_list:
            if sheet_name in sheet_names:
                try:
                    if category_name == "Time-Series":
                        # For time-series data, attempt to parse the first column as index and dates
                        df = xls.parse(sheet_name, index_col=0, parse_dates=True)
                    else:
                        df = xls.parse(sheet_name)

                    # Optional: normalize column names here if needed for consistency
                    # df = _normalize_column_names(df) # If you have a normalize function
                    parsed_data[sheet_name] = df
                    print(f"Successfully read '{sheet_name}'. Index type: {type(df.index)}. Columns: {df.columns.tolist()[:5]}...")
                except Exception as e:
                    print(f"Warning: Could not read {category_name} sheet '{sheet_name}': {e}. Storing as empty DataFrame.")
                    parsed_data[sheet_name] = pd.DataFrame()
                processed_sheet_names.add(sheet_name)
            else:
                parsed_data[sheet_name] = pd.DataFrame() # Store empty DF if missing but expected as key

    # Process any remaining sheets not explicitly categorized (custom user sheets)
    for sheet_name in sheet_names:
        if sheet_name not in processed_sheet_names:
            try:
                # For custom sheets, try to infer if the first column could be a datetime index
                # This is a heuristic. A more robust solution might require sheet naming conventions.
                try:
                    df_peek = xls.parse(sheet_name, nrows=5) # Peek at a few rows
                    first_col_is_datetime_like = False
                    if not df_peek.empty:
                        # Simple check if first column name contains 'date' or 'time' or is unnamed and first value looks like date
                        first_col_name = str(df_peek.columns[0]).lower()
                        if 'date' in first_col_name or 'time' in first_col_name or (isinstance(df_peek.iloc[0,0], (datetime.datetime, datetime.date, pd.Timestamp))):
                            first_col_is_datetime_like = True

                    if first_col_is_datetime_like:
                         df = xls.parse(sheet_name, index_col=0, parse_dates=True)
                         print(f"Successfully read custom sheet '{sheet_name}' with datetime index.")
                    else:
                         df = xls.parse(sheet_name)
                         print(f"Successfully read custom sheet '{sheet_name}' with default index.")
                except Exception: # Fallback if peeking or parsing fails
                    df = xls.parse(sheet_name)
                    print(f"Successfully read custom sheet '{sheet_name}' with default index (fallback).")

                parsed_data[sheet_name] = df
            except Exception as e:
                print(f"Warning: Could not read custom sheet '{sheet_name}': {e}. Storing as empty DataFrame.")
                parsed_data[sheet_name] = pd.DataFrame()

    xls.close() # Close the ExcelFile object
    print("PyPSA input template parsing completed.")
    return parsed_data

if __name__ == '__main__':
    # This section is for testing the PyPSA template parser directly.
    # It requires a sample Excel file named 'pypsa_input_template_test.xlsx'.

    # Example of creating a dummy PyPSA input Excel file for testing:
    # try:
    #     writer = pd.ExcelWriter("pypsa_input_template_test.xlsx", engine='openpyxl')

    #     # Settings sheet with multiple tables
    #     settings_sheet_content = {
    #         0: ["~Scenario_Info", "Parameter", "Value"],
    #         1: [None, "Scenario_Name", "Test_Scenario_2030"],
    #         2: [None, "Years", "2025,2030"],
    #         3: [None, None, None], # Empty row
    #         4: ["~CO2_Limits", "Year", "Limit_MT"],
    #         5: [None, 2025, 10.5],
    #         6: [None, 2030, 8.0],
    #         7: ["~End_CO2_Limits", None, None] # Optional end marker
    #     }
    #     pd.DataFrame(settings_sheet_content).T.to_excel(writer, sheet_name="Settings", index=False, header=False)

    #     # Component Sheet: Buses
    #     buses_data = {'name': ['Bus1', 'Bus2'], 'v_nom': [220, 110], 'country': ['IN', 'IN']}
    #     pd.DataFrame(buses_data).to_excel(writer, sheet_name="Buses", index=False)

    #     # Time-Series Sheet: Demand
    #     demand_data = {'Bus1': [100,110,105], 'Bus2': [50,55,52]} # Index will be datetime
    #     demand_idx = pd.to_datetime(['2030-01-01 00:00', '2030-01-01 01:00', '2030-01-01 02:00'])
    #     pd.DataFrame(demand_data, index=demand_idx).to_excel(writer, sheet_name="Demand") # Writes index

    #     # Cost/Parameter Sheet: Capital_cost
    #     cost_data = {'technology': ['solar_pv', 'wind_onshore'], 'value': [600, 1200], 'unit': ['EUR/kW', 'EUR/kW']}
    #     pd.DataFrame(cost_data).to_excel(writer, sheet_name="Capital_cost", index=False)

    #     # Custom sheet
    #     custom_data = {'info': ['extra_data1', 'extra_data2'], 'value': [10,20]}
    #     pd.DataFrame(custom_data).to_excel(writer, sheet_name="My_Custom_Data", index=False)

    #     writer.close()
    #     print("Dummy 'pypsa_input_template_test.xlsx' created for testing.")
    # except ImportError:
    #     print("Error: `openpyxl` is required to create the test Excel file. Please install it.")
    # except Exception as e_create:
    #     print(f"Could not create dummy PyPSA excel: {e_create}")


    test_file_path_pypsa = "pypsa_input_template_test.xlsx"

    import os
    if os.path.exists(test_file_path_pypsa):
        print(f"\n--- Testing PyPSA Template Parser with '{test_file_path_pypsa}' ---")
        pypsa_parsed_data = parse_pypsa_input_template(test_file_path_pypsa)

        if pypsa_parsed_data:
            print("\n--- Parsed PyPSA Data Summary ---")
            for key, value in pypsa_parsed_data.items():
                if key == "Settings" and isinstance(value, dict):
                    print(f"\nSheet/Section: {key} (contains multiple tables)")
                    for table_name, df_table in value.items():
                        print(f"  Table: ~{table_name} (Top 2 rows):\n{df_table.head(2)}\n")
                elif isinstance(value, pd.DataFrame):
                    print(f"\nSheet: {key} (Top 2 rows):\n{value.head(2)}\n")
                else:
                    print(f"\nSheet: {key}: Data is None or not a DataFrame (value: {value})")
        else:
            print("\n--- PyPSA Parser Test Failed: No data returned ---")
    else:
        print(f"\n--- PyPSA Parser Test Skipped: Test file '{test_file_path_pypsa}' not found. ---")
        print("Please create 'pypsa_input_template_test.xlsx' or provide path for testing.")
